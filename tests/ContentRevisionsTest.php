<?php
namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Content\RevisionedContentModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ContentRevisionsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testConcurrentHistoryWritersHaveOneWinner(): void
    {
        $root = sys_get_temp_dir() . '/gframe-history-race-' . bin2hex(random_bytes(5)); mkdir($root);
        $pdo = new \PDO('sqlite:' . $root . '/data.sqlite');
        $pdo->exec("CREATE TABLE articles(id INTEGER PRIMARY KEY,content TEXT,revision TEXT); INSERT INTO articles VALUES(1,'Original','r1')");
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-revisions/database/sqlite.sql'));
        try {
            $children = [];
            for ($i = 0; $i < 2; $i++) {
                $code = 'require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';require ' . var_export(__FILE__, true) . ';'
                    . 'define("DB_DEFAULT_CONNECTION","history");define("DB_CONNECTIONS",["history"=>["driver"=>"sqlite","path"=>' . var_export($root . '/data.sqlite', true) . ']]);'
                    . '\DatabaseManager::connection()->exec("PRAGMA busy_timeout=5000");'
                    . 'while(!is_file(' . var_export($root . '/go', true) . '))usleep(1000);'
                    . 'echo json_encode((new \GFrame\Tests\HistoryArticle())->saveWithHistory(1,"r1",["content"=>"Child ' . $i . '"],7));';
                $child = proc_open([PHP_BINARY, '-r', $code], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
                self::assertIsResource($child); fclose($pipes[0]); $children[] = [$child,$pipes];
            }
            touch($root . '/go'); $results = [];
            foreach ($children as [$child,$pipes]) { $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($child),$error);$results[]=json_decode($output,true,flags:JSON_THROW_ON_ERROR)['status']; }
            sort($results); self::assertSame(['error','success'],$results);
            self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM content_revisions')->fetchColumn());
        } finally { unset($pdo); foreach(glob($root.'/*') as $file)unlink($file);rmdir($root); }
    }

    #[RunInSeparateProcess]
    public function testHistoryRetentionRestoreIsolationAndClear(): void
    {
        define('DB_DEFAULT_CONNECTION', 'history');
        $schema = null;
        $connection = ['driver' => 'sqlite', 'path' => ':memory:'];
        if (getenv('GFRAME_HISTORY_TEST_ENGINE') === 'mysql') {
            $admin = new \PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
            $schema = 'gframe_history_test_' . bin2hex(random_bytes(6));
            $admin->exec('CREATE DATABASE `' . $schema . '`');
            $connection = ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => $schema, 'username' => 'root', 'password' => ''];
            register_shutdown_function(static function () use ($admin, $schema): void { $admin->exec('DROP DATABASE IF EXISTS `' . $schema . '`'); });
        }
        define('DB_CONNECTIONS', ['history' => $connection]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec("CREATE TABLE articles(id INTEGER PRIMARY KEY,tenant_id INTEGER,content TEXT,revision TEXT); INSERT INTO articles VALUES(1,7,'Original','r1'),(2,8,'Ajeno','r2')");
        $engine = $schema === null ? 'sqlite' : 'mysql';
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-revisions/database/' . $engine . '.sql'));
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/database/schema/' . $engine . '/auth.sql'));
        $runner = \GFrame\Install\MigrationRunner::frameworkDefault();
        $runner->baseline($pdo, $engine, ['self-account']);
        self::assertSame(1, $runner->migrate($pdo, $engine, ['content-revisions'])['count']);
        self::assertSame(0, $runner->migrate($pdo, $engine, ['content-revisions'])['count']);
        $model = new HistoryArticle(); $revision = 'r1';
        for ($i = 0; $i < 12; $i++) {
            $saved = $model->saveWithHistory(1, $revision, ['content' => '<p>Versión ' . $i . '</p>'], 7, 7, ['tenant_id' => 7]);
            self::assertSame('success', $saved['status']); $revision = $saved['data']['revision'];
        }
        $entries = $model->revisions(1, 7, ['tenant_id' => 7])['data'];
        self::assertCount(10, $entries); self::assertSame(7, (int)$entries[0]['author_id']);
        self::assertArrayNotHasKey('fields_json', $entries[0]);
        self::assertSame([], $model->revisions(1, 8, ['tenant_id' => 7])['data']);
        self::assertSame('content_conflict', $model->revisions(1, 7, ['tenant_id' => 8])['code']);
        $compare = $model->compareRevisions(1, (int)$entries[1]['id'], (int)$entries[0]['id'], 7, ['tenant_id' => 7]);
        self::assertTrue($compare['data']['content']['changed']);
        $revisionComparison = ['content' => ['before' => '<script>alert(1)</script>', 'after' => '<img src=x onerror=alert(1)>', 'changed' => true]];
        ob_start(); include dirname(__DIR__) . '/resources/modules/content-revisions/application/app/views/content-revisions/comparison.php'; $html = ob_get_clean();
        self::assertStringNotContainsString('<script>', $html); self::assertStringNotContainsString('<img ', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertSame('content_revision_not_found', $model->restoreRevision(2, (int)$entries[1]['id'], 'r2', 7, 8, ['tenant_id' => 8])['code']);
        self::assertSame('content_conflict', $model->restoreRevision(1, (int)$entries[1]['id'], 'r1', 7, 7, ['tenant_id' => 7])['code']);
        $restored = $model->restoreRevision(1, (int)$entries[1]['id'], $revision, 8, 7, ['tenant_id' => 7]);
        self::assertSame('success', $restored['status']); self::assertNotSame($revision, $restored['data']['revision']);
        self::assertSame($compare['data']['content']['before'], $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        $revision = $restored['data']['revision'];
        ConfigRepository::merge(['content' => ['revisions' => ['limit' => 2]]]);
        $saved = $model->saveWithHistory(1, $revision, ['content' => 'Final'], 7, 7, ['tenant_id' => 7]); $revision = $saved['data']['revision'];
        self::assertCount(2, $model->revisions(1, 7, ['tenant_id' => 7])['data']);
        $other = $model->saveWithHistory(2, 'r2', ['content' => 'Ajeno confirmado'], 8, 8, ['tenant_id' => 8]);
        self::assertSame('confirmation_required', $model->clearRevisions(1, $revision, false, 7, ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->clearRevisions(1, 'r1', true, 7, ['tenant_id' => 7])['code']);
        self::assertSame(2, $model->clearRevisions(1, $revision, true, 7, ['tenant_id' => 7])['data']['deleted']);
        self::assertSame([], $model->revisions(1, 7, ['tenant_id' => 7])['data']);
        self::assertCount(2, $model->revisions(2, 8, ['tenant_id' => 8])['data']);
        self::assertSame('Final', $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        self::assertSame($revision, $pdo->query('SELECT revision FROM articles WHERE id=1')->fetchColumn());
        ConfigRepository::merge(['content' => ['revisions' => ['limit' => 12]]]);
        for ($i = 0; $i < 13; $i++) {
            $saved = $model->saveWithHistory(1, $revision, ['content' => 'Final'], 7, 7, ['tenant_id' => 7]); $revision = $saved['data']['revision'];
        }
        self::assertCount(12, $model->revisions(1, 7, ['tenant_id' => 7])['data']);
        ConfigRepository::merge(['content' => ['revisions' => ['limit' => 0]]]);
        try { $model->saveWithHistory(1, $revision, ['content' => 'Inválido'], 7); self::fail('Límite cero aceptado'); }
        catch (\InvalidArgumentException) { self::assertSame('Final', $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn()); }
        ConfigRepository::merge(['content' => ['revisions' => ['limit' => 10]]]);
        $pdo->beginTransaction();
        try { $model->saveWithHistory(1, $revision, ['content' => 'No anidar'], 7); self::fail('Transacción anidada aceptada'); }
        catch (\LogicException) { self::assertTrue($pdo->inTransaction()); $pdo->rollBack(); }
        $pdo->exec('DROP TABLE content_revisions');
        try { $model->saveWithHistory(1, $revision, ['content' => 'Debe revertir'], 7, 7, ['tenant_id' => 7]); self::fail('Debe fallar sin historial'); }
        catch (\PDOException) { self::assertSame('Final', $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn()); self::assertSame($revision, $pdo->query('SELECT revision FROM articles WHERE id=1')->fetchColumn()); }
    }
}

final class HistoryArticle extends RevisionedContentModel
{
    protected $table = 'articles';
    protected $fillable = ['content', 'revision'];
    protected function historyFields(): array { return ['content']; }
}
