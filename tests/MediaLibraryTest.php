<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Media\Contracts\MediaRepository;
use GFrame\Media\MediaLibraryService;
use GFrame\Media\MediaModel;
use GFrame\Media\MediaScope;
use GFrame\Media\MediaStorage;
use GFrame\Media\MediaSyncService;
use GFrame\Media\MediaScopeResolver;
use GFrame\Media\MediaProcessor;
use GFrame\Media\RemoteMediaInspector;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class MediaLibraryTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        ConfigRepository::replace([]);
        $this->temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-media-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        ConfigRepository::replace([]);
        $this->removeDirectory($this->temporaryPath);
    }

    public function testModelImplementsRepositoryContract(): void
    {
        self::assertContains(MediaRepository::class, class_implements(MediaModel::class));
    }

    public function testUploadRejectsOversizedAndFalseImageFiles(): void
    {
        $repository = new InMemoryMediaRepository();
        $service = new MediaLibraryService($repository, new MediaStorage($this->temporaryPath));
        $file = $this->temporaryPath . DIRECTORY_SEPARATOR . 'archivo.bin';
        file_put_contents($file, 'contenido');

        $processor = $this->getMockBuilder(MediaProcessor::class)->onlyMethods(['getMaxUploadBytes'])->getMock();
        $processor->method('getMaxUploadBytes')->willReturn(4);
        $service = new MediaLibraryService($repository, new MediaStorage($this->temporaryPath), $processor);
        self::assertSame('file_size_not_allowed', $service->registerLocalFile($file, 'archivo.txt')['code']);

        $service = new MediaLibraryService($repository, new MediaStorage($this->temporaryPath));
        self::assertSame('not_image', $service->registerLocalFile($file, 'imagen.jpg')['code']);
        self::assertSame([], $repository->records);
    }

    public function testFiltersAndRelationsRemainInsideTheirScope(): void
    {
        $repository = new InMemoryMediaRepository();
        $repository->records[1] = ['media_id' => 1, 'scope_type' => 'tenant', 'scope_id' => 7, 'path' => 'uploads/tenant/7/a.txt'];
        $service = new MediaLibraryService($repository, new MediaStorage($this->temporaryPath));

        $listed = $service->paginate(0, 500, ['kind' => 'video', 'search' => '  prueba  ', 'ym' => '2026-09'], MediaScope::tenant(7));
        self::assertSame('success', $listed['status']);
        self::assertSame('videos', $repository->lastFilters['kind']);
        self::assertSame('prueba', $repository->lastFilters['search']);
        self::assertSame('2026-09', $repository->lastFilters['ym']);
        self::assertSame(1, $repository->lastPage);
        self::assertSame(100, $repository->lastPerPage);

        self::assertSame('media_not_found', $service->detach(1, 'post', 10, 'cover', MediaScope::tenant(8))['code']);
        self::assertSame('success', $service->attach(1, 'post', 10, 'cover', 0, MediaScope::tenant(7))['status']);
        self::assertSame('success', $service->detach(1, 'post', 10, 'cover', MediaScope::tenant(7))['status']);
        self::assertSame([], $service->related('post', 10, 'cover', MediaScope::tenant(8))['data']);
    }

    public function testModuleUsesPermissionsAndExceptionContracts(): void
    {
        $root = dirname(__DIR__);
        $routes = file_get_contents($root . '/resources/modules/media-library/application/routes/routes_admin_media.php')
            . file_get_contents($root . '/resources/modules/media-library/application/routes/routes_ajax_admin_media.php');
        self::assertStringContainsString('can:media.view', $routes);
        self::assertStringContainsString('can:media.add', $routes);
        self::assertStringContainsString('can:media.delete', $routes);
        self::assertStringContainsString('can:media.edit', $routes);
        self::assertStringContainsString('can:media.sync', $routes);

        foreach ([$root . '/src/GFrame/Media/MediaLibraryService.php', $root . '/resources/modules/media-library/application/app/controllers/media-library/MediaController.php'] as $file) {
            self::assertStringNotContainsString('Throwable', (string)file_get_contents($file));
        }

        foreach (['mysql.sql', 'sqlite.sql'] as $schema) {
            $sql = (string)file_get_contents($root . '/resources/modules/media-library/database/' . $schema);
            self::assertStringContainsString('alt_text', $sql);
            self::assertStringContainsString('metadata_json', $sql);
            self::assertStringContainsString('variants_json', $sql);
            self::assertStringContainsString('remote_url', $sql);
        }
        $picker = (string)file_get_contents($root . '/resources/modules/media-library/javascript/media-picker.js');
        self::assertStringContainsString('opts.endpoints', $picker);
        self::assertStringContainsString('highlightSelected()', $picker);
        self::assertFileExists($root . '/resources/modules/media-library/application/app/views/media-library/_mlist.php');
    }

    public function testMetadataQuotaAndBase64Ingestion(): void
    {
        $repository = new InMemoryMediaRepository();
        $service = new MediaLibraryService($repository, new MediaStorage($this->temporaryPath));
        $scope = MediaScope::user(4);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        ConfigRepository::replace(['media' => ['max_upload_bytes' => 2048, 'quota_bytes' => 4096]]);
        $created = $service->ingestBase64($png, 'avatar.png', $scope);
        self::assertSame('success', $created['status']);
        $mediaID = (int)$created['data']['media_id'];
        self::assertSame('success', $service->updateMetadata($mediaID, ['original_name' => 'Avatar principal', 'alt_text' => 'Retrato del usuario'], $scope)['status']);
        $details = $service->details($mediaID, $scope);
        self::assertSame('Avatar principal', $details['data']['original_name']);
        self::assertSame('Retrato del usuario', $details['data']['alt_text']);
        self::assertGreaterThan(0, $service->quota($scope)['data']['used_bytes']);

        ConfigRepository::replace(['media' => ['max_upload_bytes' => 2048, 'quota_bytes' => 1]]);
        self::assertSame('media_quota_exceeded', $service->ingestBase64($png, 'otro.png', $scope)['code']);
    }

    public function testSynchronizationImportsOnlyOriginalAllowedFiles(): void
    {
        $repository = new InMemoryMediaRepository();
        $storage = new MediaStorage($this->temporaryPath);
        $directory = $storage->uploadDirectory('library', '2026', '09', MediaScope::tenant(8));
        file_put_contents($directory['absolute'] . DIRECTORY_SEPARATOR . 'manual.txt', 'contenido');
        file_put_contents($directory['absolute'] . DIRECTORY_SEPARATOR . 'manual-small.txt', 'variante');
        file_put_contents($directory['absolute'] . DIRECTORY_SEPARATOR . 'riesgo.php', '<?php');

        $result = (new MediaSyncService($repository, $storage))->synchronize(MediaScope::tenant(8));
        self::assertSame('success', $result['status']);
        self::assertSame(1, $result['data']['added']);
        self::assertCount(1, $repository->records);
        self::assertSame('docs', $repository->records[1]['kind']);
    }

    public function testRemoteLinkKeepsScopeAndDoesNotDeleteAFile(): void
    {
        $repository = new InMemoryMediaRepository();
        $storage = new MediaStorage($this->temporaryPath);
        $inspector = $this->getMockBuilder(RemoteMediaInspector::class)->onlyMethods(['inspect'])->getMock();
        $inspector->method('inspect')->willReturn([
            'status' => 'success', 'url' => 'https://example.test/foto.jpg', 'kind' => 'images',
            'mime' => 'image/jpeg', 'size' => 1200, 'extension' => 'jpg', 'host' => 'example.test',
        ]);
        $service = new MediaLibraryService($repository, $storage, new MediaProcessor(), $inspector);
        $created = $service->registerRemoteUrl('https://example.test/foto.jpg', 'Foto externa', 'library', MediaScope::tenant(9));
        self::assertSame('success', $created['status']);
        $id = (int)$created['data']['media_id'];
        self::assertSame(0, $repository->records[$id]['size_bytes']);
        self::assertSame('https://example.test/foto.jpg', $repository->records[$id]['remote_url']);
        self::assertStringStartsWith('remote/', $repository->records[$id]['path']);
        $other = $service->registerRemoteUrl('https://example.test/foto.jpg', 'Otra foto', 'library', MediaScope::tenant(8));
        self::assertSame('success', $other['status']);
        self::assertNotSame($repository->records[$id]['path'], $repository->records[$other['data']['media_id']]['path']);
        self::assertSame('hotlink', json_decode($repository->records[$id]['metadata_json'], true)['origin']);
        self::assertSame('media_not_found', $service->delete($id, MediaScope::tenant(8))['code']);
        self::assertSame('success', $service->delete($id, MediaScope::tenant(9))['status']);
        self::assertCount(1, $repository->records);
    }

    public function testRemoteInspectorRejectsUnsafeUrlsBeforeNetworkAccess(): void
    {
        $inspector = new RemoteMediaInspector();
        $processor = new MediaProcessor();
        self::assertSame('invalid_hotlink_url', $inspector->inspect('http://example.com/file.jpg', $processor)['code']);
        self::assertSame('invalid_hotlink_host', $inspector->inspect('https://127.0.0.1/file.jpg', $processor)['code']);
        self::assertSame('invalid_hotlink_url', $inspector->inspect('https://user:pass@example.com/file.jpg', $processor)['code']);
    }

    #[RunInSeparateProcess]
    public function testFieldFragmentRejectsUnknownVariantAndKeepsScope(): void
    {
        define('ABSPATH', $this->temporaryPath . DIRECTORY_SEPARATOR);
        define('site_url', 'https://example.test/');
        $views = $this->temporaryPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'media';
        mkdir($views, 0775, true);
        mkdir($this->temporaryPath . DIRECTORY_SEPARATOR . 'public');
        \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['media-library'], $this->temporaryPath);

        $repository = new InMemoryMediaRepository();
        $repository->records[1] = [
            'media_id' => 1, 'scope_type' => 'global', 'scope_id' => null,
            'kind' => 'images', 'name' => 'foto.png', 'original_name' => 'Foto',
            'path' => 'uploads/library/2026/09/foto.png', 'alt_text' => 'Foto de prueba',
        ];
        $repository->records[2] = [
            'media_id' => 2, 'scope_type' => 'tenant', 'scope_id' => 3,
            'kind' => 'docs', 'name' => 'privado.pdf', 'original_name' => 'Privado',
            'path' => 'uploads/p/3/library/2026/09/privado.pdf', 'alt_text' => '',
        ];
        $repository->records[3] = [
            'media_id' => 3, 'scope_type' => 'global', 'scope_id' => null,
            'kind' => 'images', 'name' => 'remota.jpg', 'original_name' => 'Remota',
            'path' => 'remote/abc', 'remote_url' => 'https://example.test/remota.jpg', 'alt_text' => 'Imagen remota',
        ];
        $storage = new MediaStorage($this->temporaryPath);
        $controller = new \GFrame\Modules\MediaLibrary\Controllers\MediaController(
            new MediaLibraryService($repository, $storage),
            new MediaScopeResolver(),
            new MediaSyncService($repository, $storage, new MediaProcessor())
        );

        $_POST = ['variant' => '../../secret', 'media_ids' => '[1]'];
        self::assertSame('invalid_media_fragment', $controller->field()['code']);
        $_POST = ['variant' => 'gthumb', 'media_ids' => '[1,2,3]', 'allow_remove_one' => '0'];
        $result = $controller->field();
        self::assertSame('success', $result['status']);
        self::assertCount(2, $result['data']);
        self::assertStringContainsString('Foto de prueba', $result['html']);
        self::assertStringContainsString('class="media-thumb"', $result['html']);
        self::assertStringNotContainsString('Privado', $result['html']);
        self::assertStringContainsString('https://example.test/remota.jpg', $result['html']);
        self::assertStringNotContainsString('data-ml-media-remove', $result['html']);
    }

    public function testModulePolicyCanBeChangedByInheritanceAndPreservesOriginals(): void
    {
        $processor = new MediaProcessor();
        self::assertSame(['small', 'medium'], $processor->getVariantKeys());
        self::assertSame(150, $processor->getVariantDefinitions()['small']['w']);
        self::assertSame('fit', $processor->getVariantDefinitions()['medium']['mode']);
        $custom = new class extends MediaProcessor {
            protected function configuration(): array {
                $config = parent::configuration();
                $config['max_upload_bytes'] = 1024;
                $config['allowed_extensions'] = ['images' => ['png', 'php']];
                $config['variants'] = ['banner' => ['w' => 400, 'h' => 100, 'mode' => 'fit']];
                return $config;
            }
        };
        self::assertSame(1024, $custom->getMaxUploadBytes());
        self::assertSame(['images'], $custom->getAllowedByKind());
        self::assertSame(['png'], $custom->getAllowedByKindMap()['images']['exts']);
        self::assertSame(['banner'], $custom->getVariantKeys());
        $repository = new InMemoryMediaRepository();
        $service = new MediaLibraryService($repository, new MediaStorage($this->temporaryPath), $custom);
        $text = $this->temporaryPath . '/text.txt';
        file_put_contents($text, 'Documento');
        self::assertSame('media_not_allowed', $service->registerLocalFile($text, 'text.txt')['code']);
        self::assertSame(1024, $service->paginate()['meta']['max_upload_bytes']);
        if (!function_exists('imagecreatetruecolor')) self::markTestSkipped('GD no disponible.');
        $image = imagecreatetruecolor(600, 400);
        $source = $this->temporaryPath . '/original.png';
        imagepng($image, $source);
        imagedestroy($image);
        $hash = hash_file('sha256', $source);
        $sizes = $processor->generateVariants($this->temporaryPath, 'uploads', $source, 'original.png', 'png', 'library');
        self::assertSame($hash, hash_file('sha256', $source));
        self::assertSame([150, 150], array_slice(getimagesize($this->temporaryPath . '/original-small.png'), 0, 2));
        self::assertSame([300, 200], array_slice(getimagesize($this->temporaryPath . '/original-medium.png'), 0, 2));
        self::assertArrayNotHasKey('optimized', $sizes);
        self::assertCount(3, glob($this->temporaryPath . '/*.png'));
        $custom->generateVariants($this->temporaryPath, 'uploads', $source, 'original.png', 'png', 'library');
        self::assertSame([150, 100], array_slice(getimagesize($this->temporaryPath . '/original-banner.png'), 0, 2));
        $image = imagecreatetruecolor(20, 20);
        imagepng($image, $this->temporaryPath . '/tiny.png');
        imagedestroy($image);
        self::assertSame([], $processor->generateVariants($this->temporaryPath, 'uploads', $this->temporaryPath . '/tiny.png', 'tiny.png', 'png', 'library'));
    }

    public function testUploaderIsExplicitMetadataAndSurvivesEditing(): void
    {
        $_SESSION = ['auth' => ['id' => 999, 'name' => 'No debe leerse desde el servicio']];
        $repository = new InMemoryMediaRepository();
        $service = new MediaLibraryService($repository, new MediaStorage($this->temporaryPath));
        $file = $this->temporaryPath . '/manual.txt';
        file_put_contents($file, 'Contenido');
        $result = $service->registerLocalFile($file, 'manual.txt', 'form', MediaScope::tenant(4), ['id' => 27, 'name' => 'Juank']);
        self::assertSame('success', $result['status']);
        $id = $result['data']['media_id'];
        self::assertSame(['id' => 27, 'name' => 'Juank'], $service->details($id, MediaScope::tenant(4))['data']['metadata']['uploader']);
        $service->updateMetadata($id, ['original_name' => 'Manual', 'alt_text' => 'Documento de prueba'], MediaScope::tenant(4));
        self::assertSame(27, $service->details($id, MediaScope::tenant(4))['data']['metadata']['uploader']['id']);
        self::assertSame('media_not_found', $service->details($id, MediaScope::tenant(5))['code']);
        $_SESSION = [];
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $child = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }
        rmdir($path);
    }
}

final class InMemoryMediaRepository implements MediaRepository
{
    public array $records = [];
    public array $relations = [];
    public array $lastFilters = [];
    public int $lastPage = 0;
    public int $lastPerPage = 0;

    public function createMedia(array $media): int
    {
        $id = count($this->records) + 1;
        $this->records[$id] = ['media_id' => $id] + $media;
        return $id;
    }

    public function findMedia(int $mediaID, MediaScope $scope): ?array
    {
        $record = $this->records[$mediaID] ?? null;
        return $record !== null && $record['scope_type'] === $scope->type() && $record['scope_id'] === $scope->id() ? $record : null;
    }

    public function paginateMedia(int $page, int $perPage, MediaScope $scope, array $filters = []): array
    {
        $this->lastPage = $page;
        $this->lastPerPage = $perPage;
        $this->lastFilters = $filters;
        return ['data' => [], 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1]];
    }

    public function deleteMedia(int $mediaID, MediaScope $scope): void
    {
        unset($this->records[$mediaID]);
    }

    public function updateMedia(int $mediaID, MediaScope $scope, array $data): void
    {
        if ($this->findMedia($mediaID, $scope) !== null) $this->records[$mediaID] = array_merge($this->records[$mediaID], $data);
    }

    public function usedBytes(MediaScope $scope): int
    {
        return array_sum(array_map(static fn(array $record): int =>
            $record['scope_type'] === $scope->type() && $record['scope_id'] === $scope->id() ? (int)($record['size_bytes'] ?? 0) : 0,
            $this->records
        ));
    }

    public function paths(MediaScope $scope): array
    {
        return array_values(array_map(static fn(array $record): string => (string)$record['path'], array_filter(
            $this->records,
            static fn(array $record): bool => $record['scope_type'] === $scope->type() && $record['scope_id'] === $scope->id()
        )));
    }

    public function attach(int $mediaID, string $relatedType, int $relatedID, string $field = 'content', int $sortOrder = 0): void
    {
        $this->relations[] = compact('mediaID', 'relatedType', 'relatedID', 'field', 'sortOrder');
    }

    public function detach(int $mediaID, string $relatedType, int $relatedID, string $field = 'content'): void
    {
        $this->relations = [];
    }

    public function related(string $relatedType, int $relatedID, string $field, MediaScope $scope): array
    {
        return [];
    }
}
