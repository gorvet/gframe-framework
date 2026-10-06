<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Install\ProjectScaffolder;
use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;
use GFrame\Modules\ModuleRuntime;
use GFrame\Modules\MediaLibrary\Controllers\MediaController;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class MediaRuntimeTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testOriginalListsAndFieldsKeepAllThreeScopesAndDoNotTrustBrowserPaths(): void
    {
        define('DB_DEFAULT_CONNECTION', 'media_runtime');
        define('DB_CONNECTIONS', ['media_runtime' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/modules/media-library/database/sqlite.sql'));
        $root = sys_get_temp_dir() . '/gframe-media-runtime-' . bin2hex(random_bytes(6));
        mkdir($root . '/public', 0775, true);
        define('ABSPATH', $root . '/');
        define('site_url', 'https://example.test/project/');
        define('site_name', 'Prueba');
        ini_set('error_log', $root . '/errors.log');
        ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['media-library'], $root);
        $insert = $pdo->prepare("INSERT INTO media (scope_type, scope_id, source, kind, name, original_name, path, mime_type, size_bytes, created_at) VALUES (?, ?, ?, 'docs', ?, ?, ?, 'text/plain', 15, ?)");
        $insert->execute(['global', null, 'library', 'compartido.txt', 'Compartido', 'uploads/library/compartido.txt', '2026-01-01 00:00:00']);
        $insert->execute(['user', 27, 'profile', 'propio.txt', 'Propio', 'uploads/user/27/propio.txt', '2026-02-01 00:00:00']);
        $insert->execute(['user', 28, 'secret', 'otro.txt', 'Otro usuario', 'uploads/user/28/otro.txt', '2026-03-01 00:00:00']);
        $insert->execute(['tenant', 14, 'library', 'equipo.txt', 'Equipo', 'uploads/tenant/14/equipo.txt', '2026-04-01 00:00:00']);
        $insert->execute(['tenant', 15, 'secret', 'ajeno.txt', 'Otro tenant', 'uploads/tenant/15/ajeno.txt', '2026-05-01 00:00:00']);
        try {
            foreach ([['global', [], 1], ['user', ['auth' => ['id' => 27]], 2], ['tenant', ['company_id' => 14], 4]] as [$type, $session, $id]) {
                ConfigRepository::replace(['media' => ['scope' => $type], 'tenancy' => ['key' => 'company_id']]);
                $_SESSION = $session;
                $_REQUEST = ['tenant_id' => 15, 'source' => 'all'];
                $controller = new MediaController();
                if ($type === 'tenant') {
                    self::assertSame('media_scope_invalid', $controller->index()['code']);
                    $_REQUEST['tenant_id'] = 14;
                }
                $list = $controller->index();
                self::assertSame('success', $list['status']);
                self::assertSame([$id], array_column($list['data'], 'media_id'));
                self::assertStringContainsString('m-list-card', $list['html']);
                self::assertStringNotContainsString('pagination', $list['html']);
                self::assertStringNotContainsString('Otro usuario', $list['html']);
                self::assertStringNotContainsString('Otro tenant', $list['html']);
                self::assertNotEmpty($list['filters']['dates']);
                self::assertNotContains('secret', array_column($list['filters']['sources'], 'value'));
                self::assertStringStartsWith(site_url, $list['data'][0]['media_url']);
                $_POST = ['media_ids' => '[1,2,3,4,5]', 'variant' => 'gthumb', 'allow_remove_one' => '0', 'ctx' => '{"media_url":"https://evil.test/"}'];
                $field = $controller->field();
                self::assertSame([$id], array_column($field['data'], 'media_id'));
                self::assertStringNotContainsString('evil.test', $field['html']);
                self::assertStringNotContainsString('data-ml-media-remove', $field['html']);
                $_POST = ['media_id' => $id, 'alt_text' => 'Descripción'];
                self::assertSame('media_updated', $controller->save()['code']);
                $_REQUEST = ['q' => 'Descripción'];
                self::assertSame([$id], array_column($controller->list()['data'], 'media_id'));
                self::assertNotEmpty($pdo->query('SELECT original_name FROM media WHERE media_id = ' . $id)->fetchColumn());
                $_POST = ['media_id' => $id === 1 ? 2 : 1];
                self::assertSame('media_not_found', $controller->details()['code']);
            }
            foreach (['user', 'tenant'] as $type) {
                ConfigRepository::replace(['media' => ['scope' => $type]]);
                $_SESSION = [];
                $_REQUEST = ['tenant_id' => 14, 'user_id' => 27];
                self::assertSame('media_scope_invalid', (new MediaController())->list()['code']);
            }
            ConfigRepository::replace(['media' => ['scope' => 'global']]);
            $_SESSION = ['auth' => ['id' => 27, 'name' => 'Juank']];
            $_POST = ['name' => 'prueba.png', 'base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'uploaded_by' => 'Falso'];
            $controller = new MediaController();
            $created = $controller->base64();
            self::assertSame('success', $created['status']);
            $_POST = ['media_id' => $created['data']['media_id']];
            self::assertSame('Juank', $controller->details()['data']['uploaded_by']);
            self::assertSame('success', $controller->delete()['status']);
            $_REQUEST = ['q' => 'ausente'];
            $empty = (new MediaController())->list();
            self::assertSame([], $empty['data']);
            self::assertStringContainsString('No hay archivos que mostrar.', $empty['html']);
            self::assertStringNotContainsString('pagination', $empty['html']);
        } finally {
            ConfigRepository::replace([]);
            if (is_file($root . '/errors.log')) unlink($root . '/errors.log');
            $this->removeDirectory($root . '/public');
            rmdir($root);
        }
    }

    #[RunInSeparateProcess]
    public function testNativePresentationAndProjectPartialUseTheSameResolver(): void
    {
        $root = sys_get_temp_dir() . '/gframe-media-render-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        define('ABSPATH', $root . '/');
        define('site_url', 'https://example.test/');
        define('site_name', 'Prueba');
        try {
            ProjectScaffolder::frameworkDefault()->publish($root);
            (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))->publishProject(['admin-panel', 'media-library'], $root);
            ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['admin-panel', 'media-library'], $root);
            self::assertFileDoesNotExist($root . '/app/views/media-library/mediaIndex.php');
            $route = ['relativePath' => 'media-library', 'sourceModule' => 'media-library', 'view' => 'mediaIndex', 'templateName' => 'admin'];
            $view = (new \ReflectionClass(\Render::class))->getMethod('loadView');
            $view->setAccessible(true);
            $html = $view->invoke(new \Render(), $route, ['data' => [], 'html' => '<div>Listado original</div>']);
            self::assertStringContainsString('class="pagetitle"', $html);
            self::assertStringContainsString('knowledgeMediaAdd', $html);
            self::assertStringContainsString('editMediaModal', $html);
            self::assertStringContainsString('mediaPickerModal', $html);
            self::assertStringContainsString('Listado original', $html);
            self::assertStringNotContainsString('csrfToken', $html);
            self::assertStringNotContainsString('Base de conocimiento', $html);
            $custom = $root . '/app/views/media-library/mediaEditModal.php';
            file_put_contents($custom, '<div id="project-media-details"></div>');
            $data = ['data' => [], 'html' => ''];
            $routeParams = $route;
            ob_start();
            include ModuleRuntime::file('views', 'media-library/mediaIndex.php', 'media-library');
            $html = (string)ob_get_clean();
            self::assertStringContainsString('project-media-details', $html);
            self::assertStringNotContainsString('id="editMediaModal"', $html);
            self::assertFalse((new \ReflectionClass(MediaController::class))->isFinal());
            self::assertFalse((new \ReflectionClass(\GFrame\Media\MediaLibraryService::class))->isFinal());
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function removeDirectory(string $root): void
    {
        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $root . '/' . $name;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($root);
    }
}
