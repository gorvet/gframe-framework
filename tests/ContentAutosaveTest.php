<?php

namespace GFrame\Tests;

use GFrame\Content\{ContentDraftController, ContentDraftModel, ContentDraftService};
use GFrame\Install\MigrationRunner;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ContentAutosaveTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testPrivateScopedConditionalWritesSanitizationExpiryAndMigration(): void
    {
        define('DB_DEFAULT_CONNECTION', 'autosave');
        define('DB_CONNECTIONS', ['autosave' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-autosave/database/sqlite.sql'));
        $pdo->exec("CREATE TABLE articles (id INTEGER PRIMARY KEY, content TEXT); INSERT INTO articles VALUES (42, '<p>Publicado</p>')");
        $service = new ContentDraftService();
        self::assertNull($service->load(1, null, 'article:42:content')['data']['version']);
        $first = $service->save(1, null, 'article:42:content', '<p>Privado</p><script>alert(1)</script>', null, 'r1', 'r1');
        self::assertSame('success', $first['status']);
        self::assertStringNotContainsString('<script', $first['data']['content']);
        self::assertSame('<p>Publicado</p>', $pdo->query('SELECT content FROM articles WHERE id=42')->fetchColumn());
        foreach ([[2, null, 'article:42:content'], [1, 7, 'article:42:content'], [1, null, 'article:43:content'], [1, null, 'article:42:title']] as $scope) {
            self::assertFalse($service->load(...$scope)['data']['available']);
        }
        self::assertSame('autosave_conflict', $service->save(1, null, 'article:42:content', '<p>Stale</p>', null, 'r1', 'r1')['code']);
        self::assertSame('autosave_conflict', $service->save(1, null, 'article:42:content', '<p>Stale</p>', $first['data']['version'], 'r1', 'r2')['code']);
        $second = $service->save(1, null, 'article:42:content', '<p>Actual</p>', $first['data']['version'], 'r1', 'r1');
        self::assertSame('success', $second['status']);
        self::assertNotSame($first['data']['version'], $second['data']['version']);
        self::assertSame('autosave_conflict', $service->discard(1, null, 'article:42:content', $first['data']['version'], 'r1', 'r1')['code']);
        self::assertSame('<p>Actual</p>', $service->load(1, null, 'article:42:content')['data']['content']);
        $discard = $service->discard(1, null, 'article:42:content', $second['data']['version'], 'r1', 'r1');
        self::assertFalse($discard['data']['available']);
        self::assertNotSame($second['data']['version'], $discard['data']['version']);
        self::assertSame('autosave_conflict', $service->save(1, null, 'article:42:content', 'x', $second['data']['version'], 'r1', 'r1')['code']);
        $blank = $service->save(1, null, 'article:42:content', '', $discard['data']['version'], 'r1', 'r1');
        self::assertTrue($blank['data']['available']);
        $pdo->exec("UPDATE content_autosaves SET expires_at='2000-01-01 00:00:00'");
        $expired = $service->load(1, null, 'article:42:content')['data'];
        self::assertFalse($expired['available']); self::assertSame('', $expired['content']);
        self::assertSame($blank['data']['version'], $expired['version']);
        self::assertSame('autosave_too_large', $service->save(1, null, 'other', str_repeat('a', 1048577), null, 'r1', 'r1')['code']);
        $baseline = MigrationRunner::frameworkDefault()->baseline($pdo, 'sqlite', ['content-autosave']);
        self::assertNotEmpty(array_filter($baseline['recorded'], static fn(string $id): bool => str_contains($id, 'content-autosave')));
        self::assertSame(0, MigrationRunner::frameworkDefault()->migrate($pdo, 'sqlite', ['content-autosave'])['count']);
    }

    #[RunInSeparateProcess]
    public function testControllerUsesNormalizedIdentityAndRejectsSpoofedScope(): void
    {
        define('DB_DEFAULT_CONNECTION', 'autosave');
        define('DB_CONNECTIONS', ['autosave' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \DatabaseManager::connection()->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-autosave/database/sqlite.sql'));
        $controller = new ContentDraftController();
        $_SESSION = []; $_POST = ['action' => 'load'];
        self::assertSame('forbidden', $controller->handle('authorized:42', 'r1')['code']);
        $_SESSION = ['auth' => ['id' => 7]];
        $_POST = ['action' => 'save', 'base_version' => 'r1', 'content' => '<p>Propio</p>', 'user_id' => 8, 'resource' => 'not-authorized'];
        self::assertSame('success', $controller->handle('authorized:42', 'r1')['status']);
        self::assertTrue((new ContentDraftService())->load(7, null, 'authorized:42')['data']['available']);
        self::assertFalse((new ContentDraftService())->load(8, null, 'not-authorized')['data']['available']);
        $_POST['tenant_id'] = 99; $_SESSION['tenant_id'] = 7;
        self::assertSame('forbidden', $controller->handle('authorized:42', 'r1')['code']);
        $_SESSION = ['auth' => ['id' => 7]]; $_POST = ['action' => ['save']];
        self::assertSame('invalid_autosave', $controller->handle('authorized:42', 'r1')['code']);
    }

    #[RunInSeparateProcess]
    public function testConcurrentProcessesHaveOneWinnerForInsertAndUpdate(): void
    {
        $root = sys_get_temp_dir() . '/gframe-autosave-' . bin2hex(random_bytes(5)); mkdir($root);
        $database = $root . '/drafts.sqlite';
        $pdo = new \PDO('sqlite:' . $database);
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-autosave/database/sqlite.sql'));
        try {
            foreach ([null, $this->version($pdo)] as $expected) {
                // The second round uses the version produced by the first round.
                if ($pdo->query('SELECT COUNT(*) FROM content_autosaves')->fetchColumn()) $expected = $this->version($pdo);
                $processes = [];
                for ($index = 0; $index < 2; $index++) {
                    $code = 'require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';'
                        . 'define("DB_DEFAULT_CONNECTION","race");define("DB_CONNECTIONS",["race"=>["driver"=>"sqlite","path"=>' . var_export($database, true) . ']]);'
                        . '\DatabaseManager::connection()->exec("PRAGMA busy_timeout=5000");'
                        . 'while(!file_exists(' . var_export($root . '/go', true) . '))usleep(1000);'
                        . '$r=(new \GFrame\Content\ContentDraftService())->save(1,null,"resource","<p>Child ' . $index . '</p>",' . var_export($expected, true) . ',"r1","r1");echo json_encode($r);';
                    $pipes = []; $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
                    self::assertIsResource($process); fclose($pipes[0]); $processes[] = [$process, $pipes];
                }
                touch($root . '/go'); $statuses = [];
                foreach ($processes as [$process, $pipes]) {
                    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
                    self::assertSame(0, proc_close($process), $errors); $statuses[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['status'];
                }
                sort($statuses); self::assertSame(['error', 'success'], $statuses); unlink($root . '/go');
            }
        } finally { $pdo = null; unlink($database); rmdir($root); }
    }

    private function version(\PDO $pdo): ?string
    {
        $version = $pdo->query('SELECT version FROM content_autosaves LIMIT 1')->fetchColumn(); return $version === false ? null : $version;
    }
}
