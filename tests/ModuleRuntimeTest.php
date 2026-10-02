<?php

namespace GFrame\Tests;

use GFrame\Modules\ModuleCatalog;
use GFrame\Modules\ModuleRuntime;
use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Install\ProjectUpdateService;
use GFrame\Install\MigrationRunner;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class ModuleRuntimeTest extends TestCase
{
    private string $root;
    private ModuleCatalog $catalog;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gframe-module-runtime-' . bin2hex(random_bytes(8));
        foreach (['project/app/views/demo', 'project/app/controllers/demo', 'modules/demo/app/controllers/demo', 'modules/demo/app/models/demo', 'modules/demo/app/views/demo', 'modules/demo/app/views/templates'] as $path) mkdir($this->root . '/' . $path, 0777, true);
        file_put_contents($this->root . '/modules/demo/module.php', '<?php return ["name"=>"demo", "runtime"=>["root"=>"app", "namespace"=>"Fixture\\\\Demo", "templates"=>["demo"]]];');
        file_put_contents($this->root . '/modules/demo/app/controllers/demo/DemoController.php', '<?php namespace Fixture\\Demo\\Controllers; class DemoController { public function index(): array { return ["name"=>"native"]; } }');
        file_put_contents($this->root . '/modules/demo/app/models/demo/DemoModel.php', '<?php namespace Fixture\\Demo\\Models; class DemoModel extends \\ORM { public function label(): string { return "native-model"; } }');
        file_put_contents($this->root . '/modules/demo/app/views/demo/demoIndex.php', '<?php echo "native:" . $data["name"];');
        file_put_contents($this->root . '/modules/demo/app/views/demo/demo.group.meta.php', '<?php return ["metaTags"=>["title"=>"Native"]];');
        file_put_contents($this->root . '/modules/demo/app/views/templates/demoTemplate.php', '<?php echo $content;');
        $this->catalog = new ModuleCatalog($this->root . '/modules');
        define('ABSPATH', $this->root . '/project/');
        ModuleRuntime::initialize($this->catalog, ['demo'], $this->root . '/project');
    }

    public function testModulePresenceUsesActivatedRuntimeInsteadOfPublishedViews(): void
    {
        self::assertTrue(ModuleRuntime::has('demo'));
        self::assertFalse(ModuleRuntime::has('not-installed'));
        ModuleRuntime::initialize($this->catalog, [], $this->root . '/project');
        self::assertFalse(ModuleRuntime::has('demo'));
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function testRoutePreservesExplicitParametersAndInfersInstalledModuleOnly(): void
    {
        define('APP_LANG', 'es');
        \RouteBuilder::get('module-runtime-test', 'demo/DemoController@index')->module('demo')->template('different')->view('custom')->registerFinal();
        \RouteBuilder::get('module-runtime-inferred', 'demo/DemoController@index')->registerFinal();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = new \Router();
        $property = new \ReflectionProperty($router, 'intendedType');
        $property->setValue($router, 'web');
        $method = new \ReflectionMethod($router, 'getRouteParamsFromDeclarative');
        $params = $method->invoke($router, ['module-runtime-test']);
        self::assertSame('demo', $params['sourceModule']);
        self::assertSame('demo', $params['relativePath']);
        self::assertSame('custom', $params['view']);
        self::assertSame('different', $params['templateName']);
        $inferred = $method->invoke($router, ['module-runtime-inferred']);
        self::assertSame('demo', $inferred['sourceModule']);
        self::assertSame('demoIndex', $inferred['view']);
        self::assertNull(ModuleRuntime::inferModule('other/OtherController'));
    }

    public function testViewsMetaTemplatesAndControllersUseProjectFirstAndNativeFallback(): void
    {
        $native = ModuleRuntime::controller('demo/DemoController', 'demo');
        self::assertSame('Fixture\\Demo\\Controllers\\DemoController', $native['class']);
        self::assertSame('native', (new $native['class']())->index()['name']);
        self::assertNull(ModuleRuntime::file('views', 'demo/demoIndex.php'));
        self::assertNull(ModuleRuntime::file('views', 'demo/missing.php', 'demo'));
        self::assertNull(ModuleRuntime::file('views', '../module.php', 'demo'));
        self::assertNotNull(ModuleRuntime::template('demoTemplate.php'));
        $render = new \Render();
        $method = new \ReflectionMethod($render, 'loadView');
        $params = ['sourceModule'=>'demo', 'view'=>'demoIndex', 'relativePath'=>'demo', 'templateName'=>'demo'];
        self::assertSame('native:test', $method->invoke($render, $params, ['name'=>'test']));
        file_put_contents(ABSPATH . 'app/views/demo/custom.php', '<?php echo "project:" . $data["name"];');
        $params['view'] = 'custom';
        self::assertSame('project:test', $method->invoke($render, $params, ['name'=>'test']));
        self::assertStringContainsString('/modules/demo/', ModuleRuntime::file('views', 'demo/demo.group.meta.php', 'demo'));
        file_put_contents(ABSPATH . 'app/controllers/demo/DemoController.php', '<?php namespace App\\Controllers\\Demo; class DemoController extends \\Fixture\\Demo\\Controllers\\DemoController { public function index(): array { return ["name"=>parent::index()["name"]."+project"]; } }');
        $custom = ModuleRuntime::controller('demo/DemoController', 'demo');
        self::assertSame('App\\Controllers\\Demo\\DemoController', $custom['class']);
        self::assertSame('native+project', (new $custom['class']())->index()['name']);
    }

    public function testModelsCanExtendNativeOrmModelsWithoutNameCollisions(): void
    {
        mkdir(ABSPATH . 'app/models/demo', 0777, true);
        file_put_contents(ABSPATH . 'app/models/demo/DemoModel.php', '<?php namespace App\\Models\\Demo; class DemoModel extends \\Fixture\\Demo\\Models\\DemoModel { public function label(): string { return parent::label()."+project"; } }');
        $model = new \App\Models\Demo\DemoModel();
        self::assertInstanceOf(\ORM::class, $model);
        self::assertSame('native-model+project', $model->label());
    }

    public function testExplicitModuleSupportsAnotherRelativeFolder(): void
    {
        mkdir($this->root . '/modules/demo/app/controllers/another', 0777, true);
        file_put_contents($this->root . '/modules/demo/app/controllers/another/OtherController.php', '<?php namespace Fixture\\Demo\\Controllers\\another; class OtherController { public function index(): string { return "another"; } }');
        self::assertNull(ModuleRuntime::inferModule('another/OtherController'));
        $resolved = ModuleRuntime::controller('another/OtherController', 'demo');
        self::assertSame('Fixture\\Demo\\Controllers\\another\\OtherController', $resolved['class']);
        self::assertSame('another', (new $resolved['class']())->index());
    }

    public function testWebAndAjaxExecuteTheSameNamespacedNativeController(): void
    {
        $_SERVER['REQUEST_URI'] = '/module-runtime-test';
        mkdir(ABSPATH . 'app/views/templates', 0777, true);
        file_put_contents(ABSPATH . 'app/views/templates/header.php', '<?php echo "header:";');
        file_put_contents(ABSPATH . 'app/views/templates/footer.php', '<?php echo ":footer";');
        $params = ['controller'=>'demo/DemoController', 'actionName'=>'index', 'sourceModule'=>'demo', 'view'=>'demoIndex', 'relativePath'=>'demo', 'templateName'=>'demo'];
        ob_start();
        (new \Render())->renderView($params);
        self::assertSame('header:native:native:footer', ob_get_clean());
        $script = $this->root . '/ajax.php';
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . '; '
            . '\\GFrame\\Modules\\ModuleRuntime::initialize(new \\GFrame\\Modules\\ModuleCatalog(' . var_export($this->root . '/modules', true) . '), ["demo"], ' . var_export(ABSPATH, true) . '); '
            . '$router = new \\Router(); (new \\ReflectionProperty($router, "intendedType"))->setValue($router, "ajax"); '
            . '(new \\ReflectionMethod($router, "handleDirectExecution"))->invoke($router, ' . var_export($params, true) . ');');
        $process = proc_open([PHP_BINARY, $script], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame(['name'=>'native'], json_decode($output, true), $output . $errors);
    }

    public function testSelfAccountUsesNativeNamespaceAndPreservesItsPublicUrls(): void
    {
        $catalog = ModuleCatalog::frameworkDefault();
        ModuleRuntime::initialize($catalog, ['self-account'], ABSPATH);
        $resolved = ModuleRuntime::controller('self-account/SelfAccountController', 'self-account');
        self::assertSame('GFrame\\Modules\\SelfAccount\\Controllers\\SelfAccountController', $resolved['class']);
        self::assertFalse((new \ReflectionClass($resolved['class']))->isFinal());
        require dirname(__DIR__) . '/resources/modules/self-account/application/routes/routes_account.php';
        require dirname(__DIR__) . '/resources/modules/self-account/application/routes/routes_ajax_account.php';
        $routes = \RouteBuilder::all();
        self::assertSame('self-account', $routes['GET']['account']['sourceModule']);
        self::assertSame('self-account/SelfAccountController', $routes['GET']['account']['controller']);
        self::assertSame('auth', $routes['POST']['ajax/account/password']['middleware'][0]);
        self::assertSame('auth', $routes['POST']['ajax/account/deactivate']['middleware'][0]);
        self::assertNotNull(ModuleRuntime::file('views', 'self-account/self-accountIndex.php', 'self-account'));
    }

    public function testAuthLoadsItsNativeViewsTemplateAndHeartbeatWithoutPublishedController(): void
    {
        ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['auth-ui'], ABSPATH);
        $resolved = ModuleRuntime::controller('auth-ui/AuthController', 'auth-ui');
        self::assertSame('GFrame\\Modules\\AuthUi\\Controllers\\AuthController', $resolved['class']);
        self::assertFalse((new \ReflectionClass($resolved['class']))->isFinal());
        self::assertFileDoesNotExist(ABSPATH . 'app/controllers/auth-ui/AuthController.php');
        foreach (['authLogin', 'authRegister', 'authRecovery', 'authReset', 'authVerify'] as $view) {
            self::assertNotNull(ModuleRuntime::file('views', 'auth-ui/' . $view . '.php', 'auth-ui'));
        }
        self::assertNotNull(ModuleRuntime::template('authTemplate.php', 'auth-ui'));
        $heartbeat = new \HeartbeatMaster();
        $response = (new \ReflectionMethod($heartbeat, 'runChannel'))->invoke($heartbeat, 'auth-ui/AuthController@heartbeatSessionChannel', [], []);
        self::assertSame('success', $response['status']);
        self::assertSame('alive', $response['code']);
        ModuleRuntime::createCustomizationDirectories(ModuleCatalog::frameworkDefault()->get('auth-ui'), ABSPATH);
        file_put_contents(ABSPATH . 'app/controllers/auth-ui/AuthController.php', '<?php namespace App\\Controllers\\AuthUi; class AuthController extends \\GFrame\\Modules\\AuthUi\\Controllers\\AuthController { public function heartbeatSessionChannel(array $payload = [], array $context = []): array { return ["status"=>"success", "code"=>"custom_alive"]; } }');
        $custom = ModuleRuntime::controller('auth-ui/AuthController', 'auth-ui');
        self::assertSame('App\\Controllers\\AuthUi\\AuthController', $custom['class']);
        $customHeartbeat = (new \ReflectionMethod($heartbeat, 'runChannel'))->invoke($heartbeat, 'auth-ui/AuthController@heartbeatSessionChannel', [], []);
        self::assertSame('custom_alive', $customHeartbeat['code']);
        require dirname(__DIR__) . '/resources/modules/auth-ui/application/routes/routes_auth.php';
        require dirname(__DIR__) . '/resources/modules/auth-ui/application/routes/routes_ajax_auth.php';
        $routes = \RouteBuilder::all();
        self::assertSame('auth-ui', $routes['GET']['login']['sourceModule']);
        self::assertSame('authLogin', $routes['GET']['login']['view']);
        self::assertSame('auth-ui', $routes['POST']['ajax/logout']['sourceModule']);
    }

    public function testUserAdministrationUsesTheSameCustomPartialForInitialViewAndAjax(): void
    {
        ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['user-admin'], ABSPATH);
        ModuleRuntime::createCustomizationDirectories(ModuleCatalog::frameworkDefault()->get('user-admin'), ABSPATH);
        file_put_contents(ABSPATH . 'app/views/user-admin/_userList.php', '<?php echo "custom-user-list";');
        file_put_contents(ABSPATH . 'app/controllers/user-admin/UserAdminController.php', '<?php namespace App\\Controllers\\UserAdmin; class UserAdminController extends \\GFrame\\Modules\\UserAdmin\\Controllers\\UserAdminController { public function __construct() {} public function index(): array { return ["status"=>"success", "data"=>["users"=>["status"=>"success", "data"=>[]], "roles"=>[], "can_manage"=>false]]; } }');
        $resolved = ModuleRuntime::controller('user-admin/UserAdminController', 'user-admin');
        $controller = new $resolved['class']();
        self::assertSame('custom-user-list', $controller->list()['html']);
        $data = $controller->index();
        ob_start();
        try {
            include ModuleRuntime::file('views', 'user-admin/user-adminIndex.php', 'user-admin');
            $html = (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertStringContainsString('custom-user-list', $html);
        self::assertSame('App\\Controllers\\UserAdmin\\UserAdminController', $resolved['class']);
    }

    public function testCampaignsUseNativeFilesAndRespectCustomPartialsAndControllerInheritance(): void
    {
        ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['notification-campaigns'], ABSPATH);
        $resolved = ModuleRuntime::controller('notification-campaigns/CampaignController', 'notification-campaigns');
        self::assertSame('GFrame\\Modules\\NotificationCampaigns\\Controllers\\CampaignController', $resolved['class']);
        foreach (['index', 'form', 'automatic', 'automaticHistory'] as $view) {
            self::assertNotNull(ModuleRuntime::file('views', 'notification-campaigns/' . $view . '.php', 'notification-campaigns'));
        }
        ModuleRuntime::createCustomizationDirectories(ModuleCatalog::frameworkDefault()->get('notification-campaigns'), ABSPATH);
        file_put_contents(ABSPATH . 'app/views/notification-campaigns/_list.php', '<?php echo "custom-campaign-list";');
        file_put_contents(ABSPATH . 'app/controllers/notification-campaigns/CampaignController.php', '<?php namespace App\\Controllers\\NotificationCampaigns; class CampaignController extends \\GFrame\\Modules\\NotificationCampaigns\\Controllers\\CampaignController { protected function listResponse(): array { return ["status"=>"success", "data"=>[], "meta"=>["page"=>1,"total_pages"=>1], "can_manage"=>false]; } }');
        $custom = ModuleRuntime::controller('notification-campaigns/CampaignController', 'notification-campaigns');
        $controller = new $custom['class']();
        self::assertSame('custom-campaign-list', $controller->list()['html']);
        $data = $controller->index();
        define('site_url', 'https://example.test/');
        ob_start();
        try {
            include ModuleRuntime::file('views', 'notification-campaigns/index.php', 'notification-campaigns');
            self::assertStringContainsString('custom-campaign-list', (string)ob_get_contents());
        } finally { ob_end_clean(); }
        require dirname(__DIR__) . '/resources/modules/notification-campaigns/application/routes/routes_admin_notification_campaigns.php';
        require dirname(__DIR__) . '/resources/modules/notification-campaigns/application/routes/routes_ajax_notification_campaigns.php';
        $routes = \RouteBuilder::all();
        self::assertSame('notification-campaigns', $routes['GET']['admin/notifications/campaigns']['sourceModule']);
        self::assertSame('index', $routes['GET']['admin/notifications/campaigns']['view']);
        self::assertContains('can:notifications.campaigns.manage', $routes['POST']['ajax/admin/notifications/campaigns/create']['middleware']);
    }

    public function testNotificationsResolveNativeAndCustomizedViewsWithInheritedControllerAndService(): void
    {
        ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['notifications'], ABSPATH);
        ModuleRuntime::createCustomizationDirectories(ModuleCatalog::frameworkDefault()->get('notifications'), ABSPATH);
        $native = ModuleRuntime::controller('notifications/NotificationController', 'notifications');
        self::assertSame('GFrame\\Modules\\Notifications\\Controllers\\NotificationController', $native['class']);
        self::assertNotNull(ModuleRuntime::file('views', 'notifications/notificationsIndex.php', 'notifications'));
        self::assertNotNull(ModuleRuntime::file('views', 'notifications/parts/bell.php', 'notifications'));
        $service = new class extends \GFrame\Notifications\NotificationService {
            public function __construct() {}
            public function inbox(int $userID, int $limit = 20, ?int $tenantID = null): array { return ['status'=>'success', 'data'=>['items'=>[]], 'code'=>'notifications_loaded']; }
        };
        $controller = new $native['class']($service);
        self::assertStringContainsString('No tienes notificaciones.', $controller->inbox()['html']);
        file_put_contents(ABSPATH . 'app/views/notifications/_inbox.php', '<?php echo "custom-notification-inbox";');
        self::assertSame('custom-notification-inbox', $controller->inbox()['html']);
        file_put_contents(ABSPATH . 'app/controllers/notifications/NotificationController.php', '<?php namespace App\\Controllers\\Notifications; class NotificationController extends \\GFrame\\Modules\\Notifications\\Controllers\\NotificationController { protected function userID(): int { return 77; } }');
        $custom = ModuleRuntime::controller('notifications/NotificationController', 'notifications');
        self::assertSame('App\\Controllers\\Notifications\\NotificationController', $custom['class']);
        $instance = new $custom['class']($service);
        self::assertSame('custom-notification-inbox', $instance->heartbeatInboxChannel()['html']);
        $heartbeat = new \GFrame\Modules\HeartbeatClient\Controllers\HeartbeatController();
        $channels = (new \ReflectionProperty($heartbeat, 'registry'))->getValue($heartbeat)->all();
        self::assertArrayHasKey('notifications.inbox', $channels);
        self::assertSame(77, (new \ReflectionMethod($instance, 'userID'))->invoke($instance));
        require dirname(__DIR__) . '/resources/modules/notifications/application/routes/routes_web_notifications.php';
        require dirname(__DIR__) . '/resources/modules/notifications/application/routes/routes_ajax_notifications.php';
        $routes = \RouteBuilder::all();
        self::assertSame('notifications', $routes['GET']['notifications']['sourceModule']);
        self::assertContains('auth', $routes['POST']['ajax/notifications/mark-read']['middleware']);
    }

    public function testInstallerCreatesEmptyDirectoriesAndUpdaterPreservesCustomFiles(): void
    {
        mkdir($this->root . '/modules/demo/app/services/demo', 0777, true);
        (new ModuleAssetPublisher($this->catalog))->publishProject(['demo'], ABSPATH);
        foreach (['controllers', 'models', 'views'] as $type) self::assertDirectoryExists(ABSPATH . 'app/' . $type . '/demo');
        self::assertDirectoryDoesNotExist(ABSPATH . 'app/services/demo');
        self::assertFileDoesNotExist(ABSPATH . 'app/controllers/demo/DemoController.php');
        self::assertFileDoesNotExist(ABSPATH . 'app/views/demo/demoIndex.php');
        file_put_contents(ABSPATH . 'app/views/demo/demoIndex.php', 'my customization');
        $updater = new ProjectUpdateService($this->catalog, new MigrationRunner($this->catalog));
        $result = $updater->update(ABSPATH, ['demo']);
        self::assertSame('my customization', file_get_contents(ABSPATH . 'app/views/demo/demoIndex.php'));
        self::assertArrayNotHasKey('app/views/demo/demoIndex.php', json_decode(file_get_contents(ABSPATH . 'storage/gframe-installed.json'), true)['managed_files']);
    }

    public function testPanelErrorsAndHeartbeatUseNativeFallbackAndProjectCustomizations(): void
    {
        $catalog = ModuleCatalog::frameworkDefault();
        $modules = ['admin-panel', 'error-pages', 'notifications'];
        (new ModuleAssetPublisher($catalog))->publishProject($modules, ABSPATH);
        ModuleRuntime::initialize($catalog, $modules, ABSPATH);
        $native = ModuleRuntime::controller('admin-panel/AdminController', 'admin-panel');
        self::assertSame('GFrame\\Modules\\AdminPanel\\Controllers\\AdminController', $native['class']);
        self::assertFalse((new \ReflectionClass($native['class']))->isFinal());
        self::assertNotNull(ModuleRuntime::template('adminTemplate.php', 'self-account'));
        self::assertNotNull(ModuleRuntime::template('admin.meta.php', 'self-account'));
        self::assertNotNull(ModuleRuntime::template('errorTemplate.php'));
        self::assertDirectoryExists(ABSPATH . 'app/controllers/admin-panel');
        self::assertDirectoryExists(ABSPATH . 'app/views/admin-panel');
        self::assertDirectoryExists(ABSPATH . 'app/views/error-pages');
        self::assertDirectoryDoesNotExist(ABSPATH . 'app/controllers/error-pages');
        self::assertDirectoryDoesNotExist(ABSPATH . 'app/models/error-pages');
        self::assertDirectoryDoesNotExist(ABSPATH . 'app/services/error-pages');
        self::assertDirectoryExists(ABSPATH . 'app/controllers/heartbeat-client');
        self::assertDirectoryDoesNotExist(ABSPATH . 'app/views/heartbeat-client');
        define('site_url', 'https://example.test/');
        $routeParams = (new \ErrorResponder())->buildRouteParams('404');
        self::assertSame('error-pages', $routeParams['sourceModule']);
        file_put_contents(ABSPATH . 'app/views/error-pages/_errorCard.php', '<?php echo "custom-error-card";');
        ob_start();
        include ModuleRuntime::file('views', 'error-pages/error404.php', 'error-pages');
        self::assertSame('custom-error-card', ob_get_clean());
        if (!is_dir(ABSPATH . 'app/views/admin-panel/parts')) mkdir(ABSPATH . 'app/views/admin-panel/parts', 0775, true);
        file_put_contents(ABSPATH . 'app/views/admin-panel/parts/menu.php', '<li>custom-panel-menu</li>');
        self::assertSame(str_replace('\\', '/', realpath(ABSPATH . 'app/views/admin-panel/parts/menu.php')), ModuleRuntime::file('views', 'admin-panel/parts/menu.php', 'admin-panel'));
        file_put_contents(ABSPATH . 'app/controllers/admin-panel/AdminController.php', '<?php namespace App\\Controllers\\AdminPanel; class AdminController extends \\GFrame\\Modules\\AdminPanel\\Controllers\\AdminController { public function index(): array { return ["custom"=>true]; } }');
        $custom = ModuleRuntime::controller('admin-panel/AdminController', 'admin-panel');
        self::assertTrue((new $custom['class']())->index()['custom']);
        file_put_contents(ABSPATH . 'app/controllers/heartbeat-client/HeartbeatController.php', '<?php namespace App\\Controllers\\HeartbeatClient; class HeartbeatController extends \\GFrame\\Modules\\HeartbeatClient\\Controllers\\HeartbeatController { public function __construct() { parent::__construct(); $this->registerChannel("project.extra", [], fn()=>["status"=>"success", "data"=>["value"=>42]]); } }');
        $resolved = ModuleRuntime::controller('heartbeat-client/HeartbeatController', 'heartbeat-client');
        $heartbeat = new $resolved['class']();
        $channels = (new \ReflectionProperty($heartbeat, 'registry'))->getValue($heartbeat)->all();
        foreach (['session', 'notifications.inbox', 'project.extra'] as $channel) self::assertArrayHasKey($channel, $channels);
        $_POST['hb_force'] = 1;
        self::assertSame(42, $heartbeat->dispatch()['data']['channels']['project.extra']['data']['value']);
        unset($_POST['hb_force']);
        require dirname(__DIR__) . '/resources/modules/admin-panel/application/routes/routes_admin_dashboard.php';
        require dirname(__DIR__) . '/resources/modules/heartbeat-client/application/routes/routes_system_heartbeat.php';
        $routes = \RouteBuilder::all();
        self::assertSame('admin-panel', $routes['GET']['admin']['sourceModule']);
        self::assertSame('heartbeat-client', $routes['POST']['ajax/heartbeat']['sourceModule']);
        self::assertFalse($routes['POST']['ajax/heartbeat']['refreshSession']);
        $before = file_get_contents(ABSPATH . 'app/controllers/heartbeat-client/HeartbeatController.php');
        ProjectUpdateService::frameworkDefault()->update(ABSPATH, $modules);
        self::assertSame($before, file_get_contents(ABSPATH . 'app/controllers/heartbeat-client/HeartbeatController.php'));
        self::assertSame('<?php echo "custom-error-card";', file_get_contents(ABSPATH . 'app/views/error-pages/_errorCard.php'));
        self::assertFileDoesNotExist(ABSPATH . 'app/views/templates/adminTemplate.php');
    }
}
