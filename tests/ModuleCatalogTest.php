<?php

namespace GFrame\Tests;

use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;
use PHPUnit\Framework\TestCase;

final class ModuleCatalogTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        $this->temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-modules-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryPath);
    }

    public function testFrameworkCatalogContainsAlertsAndItsDependencies(): void
    {
        $modules = ModuleCatalog::frameworkDefault()->resolve(['alerts']);

        self::assertSame(
            ['bootstrap', 'jquery', 'sweetalert2', 'gframe-icons', 'alerts'],
            array_column($modules, 'name')
        );
    }

    public function testDefaultUiIncludesTheRequiredVisualFoundation(): void
    {
        $names = array_column(ModuleCatalog::frameworkDefault()->defaults(), 'name');

        self::assertContains('bootstrap', $names);
        self::assertContains('jquery', $names);
        self::assertContains('sweetalert2', $names);
        self::assertContains('gframe-icons', $names);
        self::assertContains('alerts', $names);
        self::assertContains('frontend-core', $names);
        self::assertContains('error-pages', $names);
    }

    public function testPublisherCopiesResolvedModuleAssets(): void
    {
        $public = $this->temporaryPath . DIRECTORY_SEPARATOR . 'public';
        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publish(['alerts'], $public);

        self::assertContains('alerts', $result['modules']);
        self::assertFileExists($public . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'internal' . DIRECTORY_SEPARATOR . 'gframe-alerts' . DIRECTORY_SEPARATOR . 'alertToast.js');
        self::assertFileExists($public . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'external' . DIRECTORY_SEPARATOR . 'sweetalert2' . DIRECTORY_SEPARATOR . 'sweetalert2.all.min.js');
    }

    public function testGfselectPublishesOnlyRuntimeAssets(): void
    {
        $public = $this->temporaryPath . DIRECTORY_SEPARATOR . 'public';
        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publish(['gfselect'], $public);

        self::assertContains('gfselect', $result['modules']);
        $directory = $public . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'internal'
            . DIRECTORY_SEPARATOR . 'gfselect';
        self::assertFileExists($directory . DIRECTORY_SEPARATOR . 'gf-select.js');
        self::assertFileExists($directory . DIRECTORY_SEPARATOR . 'gf-select.css');
        self::assertFileDoesNotExist($directory . DIRECTORY_SEPARATOR . 'demo-gfselect.html');
        self::assertFileDoesNotExist($directory . DIRECTORY_SEPARATOR . 'README.md');

        $javascript = file_get_contents($directory . DIRECTORY_SEPARATOR . 'gf-select.js');
        self::assertStringContainsString('static getInstance(', $javascript);
        self::assertStringContainsString('getValue()', $javascript);
        self::assertStringContainsString('this.select.dispatchEvent(new Event("change"', $javascript);
    }

    public function testGframeIconsStylesOnlyIconElements(): void
    {
        $module = ModuleCatalog::frameworkDefault()->get('gframe-icons');
        $css = (string)file_get_contents($module['path'] . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'style.css');

        self::assertStringContainsString('i[class^="gicon-"]', $css);
        self::assertStringContainsString('i[class*=" gicon-"]', $css);
        self::assertDoesNotMatchRegularExpression('/(^|})\s*i\s*\{/m', $css);
        self::assertFileExists($module['path'] . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'gframe-icons.woff');
        self::assertFileExists($module['path'] . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'demo.html');
    }

    public function testEveryRegisteredAssetExistsInsideItsModule(): void
    {
        foreach (ModuleCatalog::frameworkDefault()->all() as $module) {
            foreach ($module['assets'] as $asset) {
                self::assertFileExists(
                    $module['path'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset['source']),
                    'Falta un activo declarado por ' . $module['name']
                );
            }
        }
    }

    public function testExternalUiModulesPublishTheirDeclaredAssets(): void
    {
        $catalog = ModuleCatalog::frameworkDefault();
        $names = [];
        foreach ($catalog->all() as $module) {
            if (($module['type'] ?? '') === 'external-ui') {
                $names[] = $module['name'];
            }
        }

        $public = $this->temporaryPath . DIRECTORY_SEPARATOR . 'external-public';
        $result = (new ModuleAssetPublisher($catalog))->publish($names, $public);

        foreach ($names as $name) {
            self::assertContains($name, $result['modules']);
            $module = $catalog->get($name);
            foreach ($module['assets'] as $asset) {
                $source = $module['path'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset['source']);
                $target = $public . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset['target']);
                self::assertSame(is_dir($source), is_dir($target), "Destino incorrecto para {$name}");
                self::assertTrue(file_exists($target), "Falta el activo publicado de {$name}");
            }
        }
    }

    public function testEveryRegisteredSchemaExistsInsideItsModule(): void
    {
        foreach (ModuleCatalog::frameworkDefault()->all() as $module) {
            foreach ((array)($module['schemas'] ?? []) as $schema) {
                self::assertFileExists(
                    $module['path'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $schema),
                    'Falta un esquema declarado por ' . $module['name']
                );
            }
        }
    }

    public function testPublisherDoesNotOverwriteExistingFilesUnlessRequested(): void
    {
        $public = $this->temporaryPath . DIRECTORY_SEPARATOR . 'public';
        $publisher = new ModuleAssetPublisher(ModuleCatalog::frameworkDefault());
        $publisher->publish(['password-utils'], $public);

        $target = $public . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'internal' . DIRECTORY_SEPARATOR . 'passwordUtils' . DIRECTORY_SEPARATOR . 'passwordUtils.js';
        file_put_contents($target, 'personalizado');
        $publisher->publish(['password-utils'], $public);
        self::assertSame('personalizado', file_get_contents($target));

        $publisher->publish(['password-utils'], $public, true);
        self::assertNotSame('personalizado', file_get_contents($target));
    }

    public function testProjectPublisherCopiesModuleApplicationFiles(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['rich-text-editor'], $project);

        self::assertContains('rich-text-editor', $result['modules']);
        self::assertFileExists(
            $project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'richTextEditor.php'
        );
        self::assertFileExists(
            $project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'richTextEditor.meta.php'
        );
        self::assertFileExists(
            $project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js'
            . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'admin'
            . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'rich-text-editor.js'
        );
        self::assertFileExists(
            $project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'vendors'
            . DIRECTORY_SEPARATOR . 'external' . DIRECTORY_SEPARATOR . 'tinymce'
            . DIRECTORY_SEPARATOR . 'tinymce.min.js'
        );
    }

    public function testAuthUiPublishesLoginRoutesControllerAndAssets(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'auth-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['auth-ui'], $project);

        self::assertContains('auth-ui', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'AuthController.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_auth.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'authRegister.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'authRecovery.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'authReset.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'authVerify.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth.css');
    }

    public function testErrorPagesPublishViewsTemplateAndStyles(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'error-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['error-pages'], $project);

        self::assertContains('error-pages', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . '_errorCard.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'error403.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'error404.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'error500.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'error503.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'errorPermissions.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'error.group.meta.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'errorTemplate.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'error-pages' . DIRECTORY_SEPARATOR . 'error-pages.css');
    }

    public function testSelfAccountPublishesItsCompleteUiFlow(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'account-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['self-account'], $project);

        self::assertContains('self-account', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'account' . DIRECTORY_SEPARATOR . 'SelfAccountController.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_account.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_ajax_account.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'account' . DIRECTORY_SEPARATOR . 'accountIndex.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'self-account' . DIRECTORY_SEPARATOR . 'self-account.js');
    }

    public function testHeartbeatPublishesClientControllerAndRoute(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'heartbeat-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['heartbeat-client'], $project);

        self::assertContains('heartbeat-client', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'heartbeat' . DIRECTORY_SEPARATOR . 'HeartbeatController.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_system_heartbeat.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'heartbeat.js');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'session.js');
        self::assertStringContainsString(
            '->noRefreshSession()',
            (string)file_get_contents($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_system_heartbeat.php')
        );
    }

    public function testUserAdminPublishesControllerRoutesViewAndScript(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'users-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['user-admin'], $project);

        self::assertContains('user-admin', $result['modules']);
        self::assertContains('admin-panel', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'adminTemplate.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'admin.meta.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'parts' . DIRECTORY_SEPARATOR . 'menu-items' . DIRECTORY_SEPARATOR . 'users.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'admin-panel' . DIRECTORY_SEPARATOR . 'admin.js');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'UserAdminController.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_admin_users.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'usersIndex.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'user-admin' . DIRECTORY_SEPARATOR . 'user-admin.js');
    }

    public function testMediaLibraryPublishesItsManagementInterface(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'media-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['media-library'], $project);

        self::assertContains('media-library', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'MediaController.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_admin_media.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'mediaIndex.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'media-library' . DIRECTORY_SEPARATOR . 'media-library.js');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'mediaField.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'mediaPicker.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'media-library' . DIRECTORY_SEPARATOR . 'media-picker.js');
    }

    public function testNotificationsPublishQueueAdministration(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'notifications-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['notifications'], $project);

        self::assertContains('notifications', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . 'NotificationController.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_ajax_notifications.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . '_inbox.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . 'notifications.js');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'parts' . DIRECTORY_SEPARATOR . 'header-actions' . DIRECTORY_SEPARATOR . 'notifications.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'notifications.meta.php');
    }

    public function testCronRunnerPublishesCliEntryPointAndSchemas(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'cron-project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['cron-runner'], $project);

        self::assertContains('cron-runner', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'gframe-cron.php');

        $module = ModuleCatalog::frameworkDefault()->get('cron-runner');
        self::assertFileExists($module['path'] . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'mysql.sql');
        self::assertFileExists($module['path'] . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'sqlite.sql');
    }

    public function testNotificationCampaignsPublishAdminAndDatabaseFiles(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'campaign-project';
        mkdir($project, 0775, true);
        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))->publishProject(['notification-campaigns'], $project);
        self::assertContains('notification-campaigns', $result['modules']);
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . 'CampaignController.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . 'campaigns' . DIRECTORY_SEPARATOR . 'index.php');
        self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'notification-campaigns' . DIRECTORY_SEPARATOR . 'campaigns.js');
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->removeDirectory($target) : unlink($target);
        }
        rmdir($path);
    }
}
