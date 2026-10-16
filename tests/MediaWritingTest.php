<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Modules\{ModuleCatalog, ModuleRuntime};
use GFrame\Modules\MediaLibrary\Controllers\MediaController;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class MediaWritingTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testEditorFragmentsAreStrictScopedOrderedAndServerRendered(): void
    {
        define('DB_DEFAULT_CONNECTION', 'media_writing');
        define('DB_CONNECTIONS', ['media_writing' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/media-library/database/sqlite.sql'));
        $root = sys_get_temp_dir() . '/gframe-writing-' . bin2hex(random_bytes(5));
        mkdir($root . '/public', 0775, true);
        define('ABSPATH', $root . '/'); define('site_url', 'https://example.test/project/');
        ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['media-library', 'rich-text-editor'], $root);
        $insert = $pdo->prepare("INSERT INTO media (scope_type, scope_id, kind, name, original_name, path, mime_type, alt_text, created_at) VALUES (?, ?, ?, 'imagen.png', 'Imagen', ?, ?, ?, '2026-10-15')");
        foreach ([['global', null, 'images'], ['global', null, 'images'], ['user', 7, 'images'], ['tenant', 8, 'images'], ['global', null, 'docs']] as $i => [$type, $id, $kind]) {
            $insert->execute([$type, $id, $kind, 'uploads/' . $i . '.png', $kind === 'images' ? 'image/png' : 'text/plain', 'Alt "<script>"']);
        }
        try {
            ConfigRepository::replace(['media' => ['scope' => 'global']]); $_SESSION = [];
            $controller = new MediaController();
            $_POST = ['media_ids' => '[2,1,2]', 'variant' => 'editor_gallery', 'ctx' => '{"media_url":"https://evil.test/"}'];
            $result = $controller->field();
            self::assertSame('success', $result['status']);
            self::assertSame([2, 1], array_column($result['data'], 'media_id'));
            self::assertStringContainsString('<figure data-media-gallery="1">', $result['html']);
            self::assertLessThan(strpos($result['html'], 'data-media-id="1"'), strpos($result['html'], 'data-media-id="2"'));
            self::assertStringContainsString('src="https://example.test/project/public/uploads/1.png"', $result['html']);
            self::assertStringNotContainsString('evil.test', $result['html']);
            self::assertStringNotContainsString('<script>', $result['html']);
            foreach ([[1, 3], [1, 5], [0, 1], [], range(1, 51)] as $ids) {
                $_POST['media_ids'] = $ids;
                $invalid = $controller->field();
                self::assertSame('invalid_media_selection', $invalid['code']);
                self::assertArrayNotHasKey('html', $invalid);
            }
            $_POST = ['media_ids' => [1, 2], 'variant' => 'editor_image'];
            self::assertSame('invalid_media_selection', $controller->field()['code']);
            $_POST['media_ids'] = [1];
            self::assertStringContainsString('<p>', $controller->field()['html']);
            foreach ([['user', ['auth' => ['id' => 7]], 3], ['tenant', ['company_id' => 8], 4]] as [$type, $session, $id]) {
                ConfigRepository::replace(['media' => ['scope' => $type], 'tenancy' => ['key' => 'company_id']]);
                $_SESSION = $session; $_REQUEST = []; $_POST = ['variant' => 'editor_gallery', 'media_ids' => [$id]];
                self::assertSame([$id], array_column($controller->field()['data'], 'media_id'));
                $_POST['media_ids'] = [$id, 1];
                self::assertSame('invalid_media_selection', $controller->field()['code']);
            }
            $meta = require ModuleRuntime::file('views', 'rich-text-editor/richTextEditorMedia.meta.php', 'rich-text-editor');
            self::assertSame('public/js/app/admin/components/rich-text-editor.js', end($meta['js']));
            self::assertContains('public/css/modules/media-library/media-content.css', $meta['css']);
        } finally {
            ConfigRepository::replace([]); rmdir($root . '/public'); rmdir($root);
        }
    }
}
