<?php

namespace GFrame\Tests;

use GFrame\Content\{ContentPresenceController, ContentPresenceModel, ContentPresenceService, VersionedContentModel};
use GFrame\Install\MigrationRunner;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ContentEditingTest extends TestCase
{
    protected function tearDown(): void
    {
        $path = sys_get_temp_dir() . '/gframe-expected-presence-' . getmypid() . '.log';
        if (is_file($path)) unlink($path);
    }

    public function testExistingCustomDialectWithoutExactComparisonFailsClosed(): void
    {
        $dialect = $this->createStub(\DatabaseDialectInterface::class);
        $model = new class($dialect) extends \ORM {
            public function __construct(private \DatabaseDialectInterface $customDialect) { parent::__construct(); }
            protected function dialect(): \DatabaseDialectInterface { return $this->customDialect; }
        };
        $this->expectException(\LogicException::class);
        $model->whereExact('revision', 'r1');
    }

    private function database(): \PDO
    {
        define('DB_DEFAULT_CONNECTION', 'content_editing');
        define('DB_CONNECTIONS', ['content_editing' => ['driver' => 'sqlite', 'path' => ':memory:'], 'other_content' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $this->schema($pdo);
        return $pdo;
    }

    private function schema(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE articles (id INTEGER PRIMARY KEY, tenant_id INTEGER, content TEXT, revision TEXT NOT NULL); INSERT INTO articles VALUES (1,7,'Original','r1'),(2,8,'Ajeno','r1')");
    }

    #[RunInSeparateProcess]
    public function testConditionalSavePreservesScopeConnectionAndTransaction(): void
    {
        $pdo = $this->database();
        $model = new TestVersionedArticle();
        self::assertSame('content_conflict', $model->useStrictComparison(false)->saveRevision(1, 'R1', ['content' => 'Otra revisión'])['code']);
        self::assertSame('content_conflict', $model->saveRevision(1, 'r1', ['content' => 'Ajeno'], ['tenant_id' => 8])['code']);
        $saved = $model->saveRevision(1, 'r1', ['content' => 'Actual'], ['tenant_id' => 7]);
        self::assertSame('success', $saved['status']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $saved['data']['revision']);
        self::assertSame('content_conflict', $model->saveRevision(1, 'r1', ['content' => 'Obsoleto'], ['tenant_id' => 7])['code']);
        self::assertSame('Actual', $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        self::assertSame('Ajeno', $pdo->query('SELECT content FROM articles WHERE id=2')->fetchColumn());
        $same = $model->saveRevision(1, $saved['data']['revision'], ['content' => 'Actual'], ['tenant_id' => 7]);
        self::assertNotSame($saved['data']['revision'], $same['data']['revision']);
        $pdo->beginTransaction();
        self::assertSame('success', $model->saveRevision(1, $same['data']['revision'], ['content' => 'Rollback'])['status']);
        self::assertTrue($pdo->inTransaction());
        $pdo->rollBack();
        self::assertSame($same['data']['revision'], $pdo->query('SELECT revision FROM articles WHERE id=1')->fetchColumn());
        $other = \DatabaseManager::connection('other_content');
        $this->schema($other);
        self::assertSame('success', (new TestVersionedArticle())->onConnection('other_content')->saveRevision(1, 'r1', ['content' => 'Otra conexión'])['status']);
        self::assertSame('Otra conexión', $other->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        self::assertSame('Actual', $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        foreach ([['revision' => 'forged'], ['id' => 9], ['tenant_id' => 8], ['unknown' => 1]] as $fields) {
            try { $model->saveRevision(1, $same['data']['revision'], $fields, ['tenant_id' => 7]); self::fail('Campo protegido aceptado.'); }
            catch (\InvalidArgumentException) { self::assertSame('Actual', $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn()); }
        }
        $this->expectException(\LogicException::class);
        $model->where('tenant_id', '=', 7)->orWhere('id', '=', 2)->saveRevision(1, 'r1', ['content' => 'Sin scope']);
    }

    #[RunInSeparateProcess]
    public function testDocumentedModelSaveAndModuleAssets(): void
    {
        $pdo = $this->database();
        $pdo->exec('ALTER TABLE articles ADD COLUMN title TEXT');
        preg_match_all('/```php\R(.*?)\R```/s', file_get_contents(dirname(__DIR__) . '/docs/content-editing.md'), $blocks);
        eval($blocks[1][0]);
        $model = new \App\Models\Articles\ArticleModel();
        $articleID = 1; $expectedRevision = 'r1'; $title = 'Título'; $cleanHtml = '<p>Guardado</p>'; $authorizedTenantID = 7;
        eval($blocks[1][1]);
        self::assertSame('success', $result['status']);
        self::assertSame($cleanHtml, $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        $root = sys_get_temp_dir() . '/gframe-editing-assets-' . bin2hex(random_bytes(5));
        mkdir($root);
        try {
            (new \GFrame\Modules\ModuleAssetPublisher(\GFrame\Modules\ModuleCatalog::frameworkDefault()))->publishProject(['content-editing'], $root);
            self::assertFileExists($root . '/public/js/app/admin/components/content-editing.js');
            self::assertFileDoesNotExist($root . '/app/views/content-editing/contentEditing.php');
            $contentEditing = ['url' => '/presence?x="<script>', 'revision' => '"<script>'];
            ob_start(); include dirname(__DIR__) . '/resources/modules/content-editing/application/app/views/content-editing/contentEditing.php'; $html = ob_get_clean();
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringContainsString('aria-live="polite"', $html);
            $contentEditing = ['readonly' => true];
            ob_start(); include dirname(__DIR__) . '/resources/modules/content-editing/application/app/views/content-editing/contentEditing.php';
            self::assertSame('', ob_get_clean());
        } finally {
            if (is_dir($root)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                rmdir($root);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testPresenceIsolationExpiryControllerAndRepeatableMigration(): void
    {
        ini_set('error_log', sys_get_temp_dir() . '/gframe-expected-presence-' . getmypid() . '.log');
        $pdo = $this->database();
        $runner = MigrationRunner::frameworkDefault();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql'));
        $runner->baseline($pdo, 'sqlite', ['self-account']);
        self::assertGreaterThan(0, $runner->migrate($pdo, 'sqlite', ['content-editing'])['count']);
        self::assertSame(0, $runner->migrate($pdo, 'sqlite', ['content-editing'])['count']);
        $service = new ContentPresenceService();
        $a = str_repeat('a', 32); $b = str_repeat('b', 32);
        self::assertSame(0, $service->touch(1, 7, 'article:1', $a, 100)['data']['other_sessions']);
        $other = $service->touch(2, 7, 'article:1', $b, 101);
        self::assertSame(1, $other['data']['other_sessions']);
        self::assertSame(['other_sessions'], array_keys($other['data']));
        self::assertSame(1, $service->touch(1, 7, 'article:1', $a, 102)['data']['other_sessions']);
        self::assertSame(0, $service->touch(2, 8, 'article:1', $b, 102)['data']['other_sessions']);
        self::assertSame(0, $service->touch(2, 7, 'article:2', $b, 102)['data']['other_sessions']);
        self::assertSame(0, $service->touch(1, 7, 'article:1', $a, 191)['data']['other_sessions']);
        self::assertSame(1, $service->touch(2, 7, 'article:1', $b, 192)['data']['other_sessions']);
        $_SESSION = []; $_POST = ['editor_token' => $a];
        $controller = new ContentPresenceController();
        self::assertSame('forbidden', $controller->handle('authorized:1')['code']);
        $_SESSION = ['auth' => ['id' => 7], 'tenant_id' => 7];
        $_POST = ['editor_token' => [], 'tenant_id' => 8];
        self::assertSame('forbidden', $controller->handle('authorized:1')['code']);
        $_POST = ['editor_token' => []];
        self::assertSame('invalid_content_presence', $controller->handle('authorized:1')['code']);
        $_POST = ['editor_token' => $a, 'user_id' => 999, 'resource' => 'not-authorized'];
        self::assertSame('success', $controller->handle('authorized:1')['status']);
        self::assertSame(0, $service->touch(999, 7, 'not-authorized', $b)['data']['other_sessions']);
        self::assertSame(1, $service->touch(8, 7, 'authorized:1', $b)['data']['other_sessions']);
        $pdo->exec('DROP TABLE content_editing_presence');
        $failed = $controller->handle('authorized:1');
        self::assertSame('content_presence_unavailable', $failed['code']);
        self::assertStringNotContainsString('SQL', $failed['message']);
    }

    #[RunInSeparateProcess]
    public function testTenCompetitionsHaveOnlyOneSuccessfulWriter(): void
    {
        $root = sys_get_temp_dir() . '/gframe-content-editing-' . bin2hex(random_bytes(5));
        mkdir($root);
        $database = $root . '/articles.sqlite';
        $pdo = new \PDO('sqlite:' . $database);
        $configuration = ['driver' => 'sqlite', 'path' => $database];
        $schema = null;
        if (getenv('GFRAME_CONTENT_TEST_ENGINE') === 'mysql') {
            $dsn = getenv('GFRAME_TEST_MYSQL_DSN');
            if (!$dsn) self::markTestSkipped('Se requiere GFRAME_TEST_MYSQL_DSN para el perfil MySQL.');
            $pdo = new \PDO($dsn, getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '');
            $schema = 'gframe_content_test_' . bin2hex(random_bytes(6));
            $pdo->exec('CREATE DATABASE `' . $schema . '`');
            $pdo->exec('USE `' . $schema . '`');
            $parts = []; parse_str(str_replace(';', '&', substr($dsn, 6)), $parts);
            $configuration = ['driver' => 'mysql', 'host' => $parts['host'] ?? '127.0.0.1', 'port' => (int)($parts['port'] ?? 3306), 'database' => $schema, 'username' => getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', 'password' => getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: ''];
        }
        $this->schema($pdo);
        try {
            for ($round = 0; $round < 10; $round++) {
                $revision = $pdo->query('SELECT revision FROM articles WHERE id=1')->fetchColumn();
                $processes = [];
                for ($index = 0; $index < 2; $index++) {
                    $code = 'require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . '; require ' . var_export(__FILE__, true) . ';'
                        . 'define("DB_DEFAULT_CONNECTION","race");define("DB_CONNECTIONS",["race"=>' . var_export($configuration, true) . ']);'
                        . ($schema === null ? '\DatabaseManager::connection()->exec("PRAGMA busy_timeout=5000");' : '')
                        . '$deadline=microtime(true)+10;while(!file_exists(' . var_export($root . '/go', true) . ')){if(microtime(true)>$deadline)exit(24);usleep(1000);}'
                        . '$r=(new \GFrame\Tests\TestVersionedArticle())->saveRevision(1,' . var_export($revision, true) . ',["content"=>"child ' . $index . '"],["tenant_id"=>7]);echo json_encode($r);';
                    $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    self::assertIsResource($process); fclose($pipes[0]);
                    $processes[] = [$process, $pipes];
                }
                touch($root . '/go');
                $statuses = [];
                foreach ($processes as [$process, $pipes]) {
                    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
                    fclose($pipes[1]); fclose($pipes[2]);
                    self::assertSame(0, proc_close($process), $error);
                    $statuses[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR)['status'];
                }
                sort($statuses);
                self::assertSame(['error', 'success'], $statuses);
                unlink($root . '/go');
            }
        } finally {
            if ($schema !== null) $pdo->exec('DROP DATABASE `' . $schema . '`');
            unset($pdo);
            foreach (glob($root . '/*') as $file) unlink($file);
            rmdir($root);
        }
    }
}

final class TestVersionedArticle extends VersionedContentModel
{
    protected $table = 'articles';
    protected $fillable = ['content', 'revision', 'tenant_id'];
}
