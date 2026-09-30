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
            self::assertStringContainsString('Ada Ejemplo', $html);
            self::assertStringContainsString('public/css/modules/admin-panel/admin.css', $html);
            self::assertStringContainsString('public/css/variables.css', $html);
            self::assertStringNotContainsString('theme.css', $html);
            self::assertStringContainsString('data-bs-theme="light"', $html);
            self::assertStringNotContainsString('data-gf-theme', $html);
            self::assertStringNotContainsString('data-notifications-inbox', $html);

            $_SESSION['auth'] = ['email' => 'ada@example.test'];
            $fallbackHtml = $this->renderPage($render, $route);
            self::assertStringContainsString('>ada</span>', $fallbackHtml);
            self::assertStringNotContainsString('ada@example.test', $fallbackHtml);
            self::assertArrayNotHasKey('name', $_SESSION['auth']);

            $publisher->publishProject(['notifications'], $project);
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
