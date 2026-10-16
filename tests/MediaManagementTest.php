<?php

namespace GFrame\Tests;

use GFrame\Media\{MediaModel, MediaLibraryService, MediaStorage, MediaScope};
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class MediaManagementTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSortingIsAllowlistedStableScopedAndKeepsFilterCounts(): void
    {
        define('DB_DEFAULT_CONNECTION', 'media_management');
        define('DB_CONNECTIONS', ['media_management' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/media-library/database/sqlite.sql'));
        $insert = $pdo->prepare("INSERT INTO media (scope_type, scope_id, source, kind, name, original_name, path, mime_type, size_bytes, created_at) VALUES (?, ?, 'library', 'docs', ?, ?, ?, 'text/plain', ?, '2026-10-15')");
        foreach ([['global', null, 'Zeta', 300], ['global', null, 'Alfa', 100], ['global', null, 'Alfa', 100], ['tenant', 8, 'Ajeno', 1], ['user', 9, 'Propio', 1]] as $i => [$type, $id, $name, $size]) {
            $insert->execute([$type, $id, 'file-' . $i, $name, 'uploads/' . $i, $size]);
        }
        $model = new MediaModel();
        $service = new MediaLibraryService($model, new MediaStorage(sys_get_temp_dir()));
        foreach (['newest' => [3, 2, 1], 'oldest' => [1, 2, 3], 'name_asc' => [3, 2, 1], 'name_desc' => [1, 3, 2], 'size_asc' => [3, 2, 1], 'size_desc' => [1, 3, 2], 'media_id; DROP TABLE media' => [3, 2, 1]] as $sort => $ids) {
            $result = $service->paginate(1, 2, ['sort' => $sort, 'view' => 'list']);
            self::assertSame(array_slice($ids, 0, 2), array_column($result['data'], 'media_id'));
            self::assertSame(3, $result['meta']['total']);
            self::assertSame('list', $result['meta']['view']);
            $second = $service->paginate(2, 2, ['sort' => $sort]);
            self::assertSame([$ids[2]], array_column($second['data'], 'media_id'));
        }
        self::assertSame([2, 3], array_column($service->paginate(1, 24, ['search' => 'Alfa', 'sort' => 'oldest'])['data'], 'media_id'));
        self::assertSame('newest', $service->paginate(1, 24, ['sort' => 'bad'])['meta']['sort']);
        self::assertSame('grid', $service->paginate(1, 24, ['view' => '../bad'])['meta']['view']);
        self::assertSame([4], array_column($service->paginate(1, 24, ['sort' => 'name_asc'], MediaScope::tenant(8))['data'], 'media_id'));
        self::assertSame([5], array_column($service->paginate(1, 24, ['sort' => 'size_desc'], MediaScope::user(9))['data'], 'media_id'));
        self::assertSame([], $service->paginate(1, 24, ['search' => 'Ausente'])['data']);
        self::assertSame([3, 2, 1], array_column($model->paginateMedia(1, 24, MediaScope::global(), ['sort' => 'name DESC; DROP TABLE media'])['data'], 'media_id'));
    }
}
