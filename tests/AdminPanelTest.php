<?php

namespace GFrame\Tests;

use GFrame\Install\ProjectScaffolder;
use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminPanelTest extends TestCase
{
    public function testThemeChangesDoNotAddBorderThicknessToCardsOrHeader(): void
    {
        $css = (string)file_get_contents(dirname(__DIR__) . '/resources/skeleton/public/css/common.css');
        foreach (['card', 'header'] as $selector) {
            self::assertSame(1, preg_match('/(?<![\w-])\.' . $selector . '\s*\{([^}]*)\}/', $css, $base));
            self::assertStringContainsString('1px solid transparent', $base[1]);
            self::assertSame(1, preg_match('/:root\[data-bs-theme="dark"\]\s*\.' . $selector . '\s*\{([^}]*)\}/', $css, $dark));
            self::assertDoesNotMatchRegularExpression('/border(?:-bottom)?\s*:|border(?:-bottom)?-width\s*:/', $dark[1]);
        }
    }

    #[RunInSeparateProcess]
    public function testNotificationMenuRequiresAuthenticationAndMarksItsCurrentRoute(): void
    {
        define('site_url', 'https://example.test/subproject/');
        $file = dirname(__DIR__) . '/resources/modules/notifications/application/admin/notifications-menu.php';
        $routeParams = ['currentURL' => 'https://example.test/subproject/notifications'];
        $_SESSION['auth'] = [];
        ob_start();
        include $file;
        self::assertSame('', trim((string)ob_get_clean()));

        $_SESSION['auth'] = ['id' => 1];
        ob_start();
        include $file;
        $html = (string)ob_get_clean();
        self::assertStringContainsString('class="nav-link current"', $html);
        self::assertStringContainsString('href="https://example.test/subproject/notifications"', $html);
        self::assertStringContainsString('>Notificaciones</span>', $html);

        $routeParams['currentURL'] = 'https://example.test/subproject/admin';
        ob_start();
        include $file;
        self::assertStringNotContainsString('nav-link current', (string)ob_get_clean());
    }

    public function testSharedThemeAndAdministrativeFooterFollowThePanelContract(): void
    {
        $root = dirname(__DIR__);
        $common = (string)file_get_contents($root . '/resources/skeleton/public/css/common.css');
        $admin = (string)file_get_contents($root . '/resources/modules/admin-panel/public/admin.css');
        self::assertStringNotContainsString('data-gf-theme', $common);
        self::assertStringContainsString(':root[data-bs-theme="dark"] .navbar', $common);
        self::assertStringContainsString('--nav-link-color: var(--bs-body-color)', $common);
        self::assertStringContainsString('.tpl-admin #footer.gframe-footer .footer-credits .container', $admin);
        self::assertStringContainsString('text-align: left !important', $admin);
        self::assertStringContainsString('color: var(--bs-secondary-color)', $admin);
    }

    #[RunInSeparateProcess]
    public function testAdministrativeTemplateRendersWithAndWithoutNotificationContribution(): void
    {
        $project = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-admin-panel-' . bin2hex(random_bytes(6));
        mkdir($project, 0775, true);
        try {
            ProjectScaffolder::frameworkDefault()->publish($project);
            self::assertFileExists($project . '/app/views/templates/mail/mailTemplate.html');
            self::assertFileExists($project . '/app/views/templates/mail/contactTemplate.html');
            $publisher = new ModuleAssetPublisher(ModuleCatalog::frameworkDefault());
            $publisher->publishProject(['admin-panel'], $project);
            mkdir($project . '/app/views/admin-panel/parts/menu-items', 0775, true);
            file_put_contents($project . '/app/views/admin-panel/parts/menu-items/test.php', '<li class="nav-item"><span>Gestión de usuarios</span></li>');
            file_put_contents($project . '/app/views/admin-panel/parts/menu-items/user-test.php', '<?php $menuSection = "user"; ?><li class="nav-heading">Multimedia</li><li class="nav-item"><span>Biblioteca multimedia</span></li>');
            self::assertFileExists($project . '/config/routes/routes_admin_dashboard.php');
            self::assertFileDoesNotExist($project . '/app/views/admin-panel/adminIndex.php');
            \GFrame\Modules\ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['admin-panel'], $project);
            self::assertNotNull(\GFrame\Modules\ModuleRuntime::file('views', 'admin-panel/adminIndex.php', 'admin-panel'));
            $viewDirectory = $project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'reports';
            mkdir($viewDirectory, 0775, true);
            file_put_contents($viewDirectory . DIRECTORY_SEPARATOR . 'reportIndex.php', '<div class="col-12">Informe de prueba</div>');

            define('ABSPATH', $project . DIRECTORY_SEPARATOR);
            define('site_url', 'https://example.test/');
            define('site_name', 'Proyecto de prueba');
            $_SERVER['SCRIPT_NAME'] = '/index.php';
            $_SERVER['REQUEST_URI'] = '/admin/reports';
            $_SESSION['auth'] = ['name' => 'Ada Ejemplo', 'email' => 'ada@example.test'];

            $route = ['templateName' => 'admin', 'relativePath' => 'admin/reports', 'view' => 'reportIndex', 'currentURL' => 'https://example.test/admin/reports'];
            $render = new \Render();
            $html = $this->renderPage($render, $route);
            self::assertStringContainsString('Informe de prueba', $html);
            $sidebarStart = strpos($html, '<ul class="sidebar-nav"');
            $sidebar = substr($html, $sidebarStart, strpos($html, '</ul>', $sidebarStart) - $sidebarStart);
            self::assertTrue(strpos($sidebar, '>Escritorio</span>') < strpos($sidebar, '>Administración</li>'));
            self::assertTrue(strpos($sidebar, '>Escritorio</span>') < strpos($sidebar, '>Multimedia</li>'));
            self::assertTrue(strpos($sidebar, '>Biblioteca multimedia</span>') < strpos($sidebar, '>Administración</li>'));
            self::assertTrue(strpos($sidebar, '>Administración</li>') < strpos($sidebar, '>Gestión de usuarios</span>'));
            self::assertTrue(strpos($sidebar, '>Gestión de usuarios</span>') < strpos($sidebar, '>Mi cuenta</li>'));
            self::assertStringEndsWith('</a></li>', trim(substr($sidebar, strpos($sidebar, '>Mi cuenta</li>'))));
            self::assertStringContainsString('Ada Ejemplo', $html);
            self::assertStringContainsString('public/css/modules/admin-panel/admin.css', $html);
            self::assertStringContainsString('public/css/variables.css', $html);
            self::assertStringNotContainsString('theme.css', $html);
            self::assertStringContainsString('data-bs-theme="light"', $html);
            self::assertStringNotContainsString('data-gf-theme', $html);
            self::assertStringNotContainsString('data-notifications-inbox', $html);

            $_SESSION['auth'] = ['email' => 'ada@example.test'];
            $fallbackHtml = $this->renderPage($render, $route);
            self::assertStringContainsString('>Hola, ada</span>', $fallbackHtml);
            self::assertStringNotContainsString('ada@example.test', $fallbackHtml);
            self::assertArrayNotHasKey('name', $_SESSION['auth']);
            $_SESSION['auth'] = ['name' => '<Ada>', 'email' => 'ada@example.test'];
            self::assertStringContainsString('Hola, &lt;Ada&gt;</span>', $this->renderPage($render, $route));

            $publisher->publishProject(['notifications'], $project);
            \GFrame\Modules\ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['admin-panel', 'notifications'], $project);
            $html = $this->renderPage($render, $route);
            self::assertStringContainsString('data-notifications-inbox', $html);
            self::assertStringContainsString('public/js/modules/notifications/notifications.js', $html);
        } finally {
            $this->removeDirectory($project);
        }
    }

    private function renderPage(\Render $render, array $route): string
    {
        \Meta::getInstance()->reset();
        $method = (new ReflectionClass(\Render::class))->getMethod('loadView');
        $method->setAccessible(true);
        $content = $method->invoke($render, $route, []);
        ob_start();
        $render->loadTemplate($content, $route, []);
        return (string)ob_get_clean();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->removeDirectory($target) : unlink($target);
        }
        rmdir($path);
    }
}
