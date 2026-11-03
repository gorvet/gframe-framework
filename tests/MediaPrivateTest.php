<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Media\{MediaModel, MediaLibraryService, MediaStorage, MediaScope, MediaAccessPolicy, MediaSyncService, MediaProcessor, RemoteMediaInspector};
use GFrame\Install\{MigrationRunner, SchemaInstaller};
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class MediaPrivateTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testPrivateLibraryKeepsPermissionsScopesVariantsAndGrants(): void
    {
        define('DB_DEFAULT_CONNECTION', 'media_private');
        define('DB_CONNECTIONS', ['media_private' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/media-library/database/sqlite.sql'));
        MigrationRunner::frameworkDefault()->migrate($pdo, 'sqlite', ['runtime-storage']);
        $root = sys_get_temp_dir() . '/gframe-private-media-' . bin2hex(random_bytes(6));
        mkdir($root . '/public', 0775, true);
        ini_set('error_log', $root . '/errors.log');
        define('ABSPATH', $root . '/');
        define('site_url', 'https://example.test/project/');
        $storage = new MediaStorage($root . '/public', $root . '/private');
        $model = new MediaModel();
        $trusted = new MediaLibraryService($model, $storage);
        $scope = MediaScope::tenant(8);
        ConfigRepository::replace(['media' => ['sources' => ['restricted' => ['visibility' => 'private', 'private_permission' => 'restricted.files']]],
            'files' => ['downloads' => ['enabled' => true, 'audit' => false]]]);
        $file = $root . '/input.txt';
        file_put_contents($file, 'Contenido privado');
        try {
            $public = $trusted->registerLocalFile($file, 'Publico.txt', 'library', $scope)['data']['media_id'];
            $private = $trusted->registerLocalFile($file, 'Privado.txt', 'library', $scope, [], 'private')['data']['media_id'];
            $restricted = $trusted->registerLocalFile($file, 'Restringido.txt', 'restricted', $scope)['data']['media_id'];
            $trusted->registerLocalFile($file, 'Otro tenant.txt', 'library', MediaScope::tenant(9), [], 'private');
            $deny = new MediaLibraryService($model, $storage, access: new MediaAccessPolicy(static fn() => false));
            self::assertSame([$public], array_column($deny->paginate(scope: $scope)['data'], 'media_id'));
            self::assertSame(1, $deny->paginate(scope: $scope)['meta']['total']);
            self::assertSame(['library'], array_column($deny->paginate(scope: $scope)['filters']['sources'], 'value'));
            self::assertSame('media_not_found', $deny->details($private, $scope)['code']);
            self::assertSame('media_not_found', $deny->delete($private, $scope)['code']);
            self::assertSame('media_not_found', $deny->updateMetadata($private, ['original_name' => 'No'], $scope)['code']);
            self::assertSame('media_forbidden', $deny->registerLocalFile($file, 'No.txt', 'restricted', $scope)['code']);
            $permitted = new MediaLibraryService($model, $storage, access: new MediaAccessPolicy(static fn(string $permission) => str_starts_with($permission, 'media.private.')));
            self::assertSame([$private, $public], array_column($permitted->paginate(scope: $scope)['data'], 'media_id'));
            self::assertSame('media_not_found', $permitted->details($restricted, $scope)['code']);
            self::assertSame('media_not_found', $permitted->details($private, MediaScope::tenant(9))['code']);
            self::assertSame([$private], array_column($permitted->paginate(filters: ['visibility' => 'private'], scope: $scope)['data'], 'media_id'));
            self::assertSame('private_hotlink_not_allowed', $trusted->registerRemoteUrl('https://example.test/file', source: 'restricted', scope: $scope)['code']);
            $grant = $permitted->issueDownload($private, $scope, ['ttl_seconds' => 0]);
            $downloads = new \GFrame\Files\PrivateDownloadService($storage->privateFiles());
            self::assertSame(200, $downloads->resolve($grant['token'])->status());
            $permitted->revokeDownload($private, $grant['grant_id'], $scope);
            try { $downloads->resolve($grant['token']); self::fail('Enlace revocado.'); } catch (\RuntimeException) { self::assertTrue(true); }

            \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['media-library'], $root);
            $controller = new class($permitted) extends \GFrame\Modules\MediaLibrary\Controllers\MediaController {
                public function present(array $item): array { return $this->viewItem($item); }
            };
            $item = $controller->present($permitted->details($private, $scope)['data']);
            self::assertStringContainsString('/admin/media-file?media_id=', $item['media_url']);
            self::assertArrayNotHasKey('path', $item);
            self::assertArrayNotHasKey('variants', $item);
            self::assertSame('success', $trusted->changeVisibility($public, 'private', $scope)['status']);
            $converted = $trusted->details($public, $scope)['data'];
            self::assertSame('private', $converted['visibility']);
            self::assertFileExists($storage->absolute($converted['path'], 'private'));
            self::assertSame('success', $trusted->changeVisibility($public, 'public', $scope)['status']);
            self::assertFileDoesNotExist($storage->absolute($converted['path'], 'private'));
            self::assertSame('success', $trusted->delete($private, $scope)['status']);
            self::assertSame('media_not_found', $trusted->details($private, $scope)['code']);

            if (function_exists('imagecreatetruecolor')) {
                $image = imagecreatetruecolor(600, 400);
                imagepng($image, $root . '/input.png');
                imagedestroy($image);
                $created = $trusted->registerLocalFile($root . '/input.png', 'Imagen.png', 'library', $scope, [], 'private');
                self::assertSame('success', $created['status']);
                $imageID = (int)$created['data']['media_id'];
                $imageMedia = $trusted->details($imageID, $scope)['data'];
                self::assertCount(2, array_unique($imageMedia['variants']));
                foreach ($imageMedia['variants'] as $variant) {
                    self::assertFileExists($storage->absolute($variant, 'private'));
                    self::assertFileDoesNotExist($root . '/public/' . $variant);
                }
                self::assertSame(200, $permitted->fileResponse($imageID, $scope, 'small')->status());
                $broken = new MediaLibraryService(new class extends MediaModel {
                    public function updateMedia(int $mediaID, MediaScope $scope, array $data): void { throw new \RuntimeException('Fallo simulado'); }
                }, $storage);
                self::assertSame('error', $broken->changeVisibility($imageID, 'public', $scope)['status']);
                self::assertFileExists($storage->absolute($imageMedia['path'], 'private'));
                self::assertSame('private', $trusted->details($imageID, $scope)['data']['visibility']);
                self::assertSame('success', $trusted->changeVisibility($imageID, 'public', $scope)['status']);
                foreach ([$imageMedia['path'], ...array_values($imageMedia['variants'])] as $path) self::assertFileDoesNotExist($storage->absolute($path, 'private'));
            }

            // Private synchronization is explicit and cannot import another scope.
            $directory = $storage->uploadDirectory('imported', '2026', '11', $scope, 'private');
            file_put_contents($directory['absolute'] . '/scan.txt', 'Importado');
            self::assertSame(1, (new MediaSyncService($model, $storage))->synchronize($scope, 'private')['data']['added']);
            self::assertSame('private', $pdo->query("SELECT visibility FROM media WHERE original_name = 'scan.txt'")->fetchColumn());
        } finally {
            ConfigRepository::replace([]);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            rmdir($root);
        }
    }

    public function testMigrationKeepsExistingFilesPublicAndIsBaselinedForFreshInstalls(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $schema = file_get_contents(dirname(__DIR__) . '/resources/modules/media-library/database/sqlite.sql');
        $pdo->exec(str_replace("  visibility TEXT NOT NULL DEFAULT 'public',\n", '', str_replace("\r\n", "\n", $schema)));
        $pdo->exec("INSERT INTO media (kind, name, original_name, path, mime_type, created_at) VALUES ('docs', 'old', 'old', 'uploads/old.txt', 'text/plain', '2026-01-01')");
        $runner = MigrationRunner::frameworkDefault();
        $runner->baseline($pdo, 'sqlite', ['media-library']);
        $pdo->exec("DELETE FROM gframe_migrations WHERE migration LIKE '%202611010001_media_visibility%'");
        self::assertSame(1, $runner->migrate($pdo, 'sqlite', ['media-library'])['count']);
        self::assertSame('public', $pdo->query('SELECT visibility FROM media')->fetchColumn());
        self::assertSame(0, $runner->migrate($pdo, 'sqlite', ['media-library'])['count']);
        $fresh = new \PDO('sqlite::memory:');
        SchemaInstaller::frameworkDefault()->install($fresh, 'sqlite', true, ['media-library']);
        $runner->baseline($fresh, 'sqlite', ['media-library']);
        self::assertSame(0, $runner->migrate($fresh, 'sqlite', ['media-library'])['count']);
    }
}
