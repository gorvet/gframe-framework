<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Media\{MediaModel, MediaLibraryService, MediaStorage, MediaScope, MediaProcessor, MediaAccessPolicy};
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class MediaOptimizationTest extends TestCase
{
    public function testOptimizationCompareAndSwapOnMysql(): void
    {
        $dsn = getenv('GFRAME_TEST_MYSQL_DSN');
        if (!$dsn) self::markTestSkipped('Se requiere MySQL de pruebas.');
        $pdo = new \PDO($dsn, getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TEMPORARY TABLE media_optimization_entries (media_id BIGINT PRIMARY KEY, scope_type VARCHAR(20), scope_id BIGINT, path TEXT, visibility VARCHAR(20), variants_json TEXT, metadata_json TEXT, size_bytes BIGINT)');
        $pdo->exec("INSERT INTO media_optimization_entries VALUES (1, 'tenant', 7, 'media/original.png', 'private', '{}', '{}', 100)");
        MysqlOptimizationModel::attach($pdo);
        try {
            $model = new MysqlOptimizationModel();
            $scope = MediaScope::tenant(7);
            $expected = $model->findMedia(1, $scope);
            $replacement = ['variants_json' => '{"optimized":"media/copy.webp"}', 'metadata_json' => '{"optimized":{"size_bytes":40}}', 'size_bytes' => 140];
            self::assertFalse($model->saveOptimization(1, MediaScope::tenant(8), $expected, $replacement));
            self::assertTrue($model->saveOptimization(1, $scope, $expected, $replacement));
            self::assertFalse($model->saveOptimization(1, $scope, $expected, $replacement));
            self::assertSame(140, (int)$model->findMedia(1, $scope)['size_bytes']);
            self::assertSame('private', $model->findMedia(1, $scope)['visibility']);
        } finally { \ORM::disconnect('media_optimization_mysql'); }
    }

    #[RunInSeparateProcess]
    public function testExistingImagesOptimizeInPlaceAndKeepScopePrivacyMetadataAndThumbnails(): void
    {
        if (!(new MediaProcessor())->gdSupportsWebp()) self::markTestSkipped('GD con WebP no disponible.');
        define('DB_DEFAULT_CONNECTION', 'media_optimization');
        define('DB_CONNECTIONS', ['media_optimization' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/media-library/database/sqlite.sql'));
        $root = sys_get_temp_dir() . '/gframe-optimize-' . bin2hex(random_bytes(6));
        mkdir($root . '/public', 0775, true);
        ini_set('error_log', $root . '/errors.log');
        $storage = new MediaStorage($root . '/public', $root . '/private');
        $model = new MediaModel();
        $legacyProcessor = new class extends MediaProcessor {
            protected function configuration(): array {
                $config = parent::configuration(); unset($config['variants']['optimized']); return $config;
            }
        };
        $legacy = new MediaLibraryService($model, $storage, $legacyProcessor);
        $service = new MediaLibraryService($model, $storage);
        $scope = MediaScope::tenant(7);
        $image = imagecreatetruecolor(800, 400);
        imagepng($image, $root . '/input.png', 0); imagedestroy($image);
        try {
            foreach (['public', 'private'] as $visibility) {
                $created = $legacy->registerLocalFile($root . '/input.png', 'Portada.png', 'pages', $scope, ['id' => 9, 'name' => 'Autora'], $visibility);
                $id = $created['data']['media_id'];
                $legacy->updateMetadata($id, ['original_name' => 'Portada visible', 'alt_text' => 'Descripción'], $scope);
                $before = $legacy->details($id, $scope)['data'];
                $hash = hash_file('sha256', $storage->absolute($before['path'], $visibility));
                self::assertTrue($service->canOptimize($before));
                self::assertSame('media_not_found', $service->optimize($id, MediaScope::tenant(8))['code']);
                $denied = new MediaLibraryService($model, $storage, access: new MediaAccessPolicy(static fn() => false));
                if ($visibility === 'private') self::assertSame('media_not_found', $denied->optimize($id, $scope)['code']);
                self::assertSame('media_optimized', $service->optimize($id, $scope)['code']);
                $after = $service->details($id, $scope)['data'];
                foreach (['media_id', 'path', 'original_name', 'alt_text', 'visibility', 'created_at'] as $key) self::assertSame($before[$key], $after[$key]);
                self::assertSame($before['metadata']['uploader'], $after['metadata']['uploader']);
                foreach ($before['variants'] as $key => $path) self::assertSame($path, $after['variants'][$key]);
                $webp = $storage->absolute($after['variants']['optimized'], $visibility);
                self::assertSame('image/webp', getimagesize($webp)['mime']);
                self::assertSame($before['size_bytes'] + filesize($webp), $after['size_bytes']);
                self::assertSame($hash, hash_file('sha256', $storage->absolute($before['path'], $visibility)));
                self::assertFalse($service->canOptimize($after));
                self::assertSame('media_already_optimized', $service->optimize($id, $scope)['code']);
                self::assertSame($after['size_bytes'], $service->details($id, $scope)['data']['size_bytes']);
                if ($visibility === 'private') {
                    self::assertFileDoesNotExist($root . '/public/' . $after['variants']['optimized']);
                    self::assertSame('image/webp', $service->fileResponse($id, $scope, 'optimized')->headers()['Content-Type']);
                }
            }
            $id = $legacy->registerLocalFile($root . '/input.png', 'Cuota.png', 'library', $scope)['data']['media_id'];
            $before = $legacy->details($id, $scope)['data'];
            $files = glob(dirname($storage->absolute($before['path'])) . '/*');
            ConfigRepository::replace(['media' => ['quota_bytes' => $model->usedBytes($scope)]]);
            self::assertSame('media_quota_exceeded', $service->optimize($id, $scope)['code']);
            self::assertSame($files, glob(dirname($storage->absolute($before['path'])) . '/*'));
            self::assertSame($before['variants'], $service->details($id, $scope)['data']['variants']);
            ConfigRepository::replace([]);
            $conflict = new MediaLibraryService(new class extends MediaModel {
                public function saveOptimization(int $mediaID, MediaScope $scope, array $expected, array $replacement): bool { return false; }
            }, $storage);
            self::assertSame('media_optimization_conflict', $conflict->optimize($id, $scope)['code']);
            self::assertSame($files, glob(dirname($storage->absolute($before['path'])) . '/*'));
            // El CAS rechaza un snapshot anterior y no pierde una modificación concurrente.
            $raw = $model->findMedia($id, $scope);
            $model->updateMedia($id, $scope, ['metadata_json' => '{"concurrent":true}']);
            self::assertFalse($model->saveOptimization($id, $scope, $raw, ['variants_json' => '{"optimized":"no.webp"}']));
            self::assertTrue($service->details($id, $scope)['data']['metadata']['concurrent']);
        } finally {
            ConfigRepository::replace([]);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            rmdir($root);
        }
    }
}

final class MysqlOptimizationModel extends MediaModel
{
    protected $table = 'media_optimization_entries';
    protected $connection = 'media_optimization_mysql';
    public static function attach(\PDO $pdo): void { self::$pdo['media_optimization_mysql'] = $pdo; }
    protected function dialect(): \DatabaseDialectInterface { return new \MySqlDialect(); }
}
