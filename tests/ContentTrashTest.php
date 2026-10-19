<?php
namespace GFrame\Tests;

use GFrame\Content\{ContentTrash, VersionedContentModel, RevisionedContentModel};
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ContentTrashTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testLifecycleIsolationExactRevisionAndTransactionalCleanup(): void
    {
        $connection = ['driver' => 'sqlite', 'path' => ':memory:'];
        if (getenv('GFRAME_TRASH_TEST_ENGINE') === 'mysql') {
            $admin = new \PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
            $schema = 'gframe_trash_test_' . bin2hex(random_bytes(6));
            $admin->exec('CREATE DATABASE `' . $schema . '`');
            register_shutdown_function(static function () use ($admin, $schema): void { $admin->exec('DROP DATABASE IF EXISTS `' . $schema . '`'); });
            $connection = ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => $schema, 'username' => 'root', 'password' => ''];
        }
        define('DB_DEFAULT_CONNECTION', 'unused');
        define('DB_CONNECTIONS', ['trash' => $connection]);
        $pdo = \DatabaseManager::connection('trash');
        $pdo->exec('CREATE TABLE articles(id INTEGER PRIMARY KEY,tenant_id INTEGER,content TEXT,revision VARCHAR(128),deleted_at DATETIME NULL); CREATE TABLE article_links(id INTEGER PRIMARY KEY,article_id INTEGER)');
        $pdo->exec("INSERT INTO articles VALUES(1,7,'Original','R1',NULL),(2,8,'Ajeno','r2',NULL); INSERT INTO article_links VALUES(1,1),(2,2)");
        $model = (new TrashArticle())->onConnection('trash');
        self::assertCount(1, $model->activeContent(['tenant_id' => 7])->get());
        self::assertSame([], $model->trashedContent(['tenant_id' => 7])->get());
        self::assertSame('content_conflict', $model->trashContent(1, 'r1', ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->trashContent(1, 'R1 ', ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->trashContent(1, 'R1', ['tenant_id' => 8])['code']);
        self::assertSame('content_conflict', $model->restoreContent(1, 'R1', ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->deleteTrashedContent(1, 'R1', true, ['tenant_id' => 7])['code']);
        $trash = $model->trashContent(1, 'R1', ['tenant_id' => 7]);
        self::assertSame('content_trashed', $trash['code']); $revision = $trash['data']['revision'];
        self::assertNotSame('R1', $revision);
        self::assertSame([], $model->activeContent(['tenant_id' => 7])->get());
        self::assertCount(1, $model->trashedContent(['tenant_id' => 7])->get());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM article_links')->fetchColumn());
        self::assertSame('content_conflict', $model->trashContent(1, $revision, ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->restoreContent(1, 'R1', ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->saveRevision(1, $revision, ['content' => 'Blocked'], ['tenant_id' => 7, 'deleted_at' => null])['code']);
        $restored = $model->restoreContent(1, $revision, ['tenant_id' => 7]);
        self::assertSame('content_restored', $restored['code']);
        self::assertSame('Original', $pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        self::assertSame(null, $pdo->query('SELECT deleted_at FROM articles WHERE id=1')->fetchColumn());
        self::assertSame('content_conflict', $model->restoreContent(1, $restored['data']['revision'])['code']);
        $revision = $model->trashContent(1, $restored['data']['revision'], ['tenant_id' => 7])['data']['revision'];
        self::assertSame('confirmation_required', $model->deleteTrashedContent(1, $revision, false, ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->deleteTrashedContent(1, 'R1', true, ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->deleteTrashedContent(1, $revision, true, ['tenant_id' => 8])['code']);
        $model->failCleanup = true;
        try { $model->deleteTrashedContent(1, $revision, true, ['tenant_id' => 7]); self::fail('Cleanup failure ignored'); }
        catch (\RuntimeException $exception) { self::assertSame('cleanup_failed', $exception->getMessage()); }
        self::assertSame($revision, $pdo->query('SELECT revision FROM articles WHERE id=1')->fetchColumn());
        self::assertCount(1, $model->trashedContent(['tenant_id' => 7])->get());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM article_links')->fetchColumn());
        $pdo->beginTransaction();
        try { $model->deleteTrashedContent(1, $revision, true); self::fail('Nested transaction accepted'); }
        catch (\LogicException) { self::assertTrue($pdo->inTransaction()); $pdo->rollBack(); }
        try { $model->where('tenant_id', '=', 8)->orWhere('id', '=', 1)->trashContent(2, 'r2'); self::fail('Pending OR accepted'); }
        catch (\LogicException) { self::assertSame('r2', $pdo->query('SELECT revision FROM articles WHERE id=2')->fetchColumn()); }
        $model->reset(); $model->failCleanup = false;
        self::assertSame('content_deleted', $model->deleteTrashedContent(1, $revision, true, ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->deleteTrashedContent(1, $revision, true, ['tenant_id' => 7])['code']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT article_id FROM article_links')->fetchColumn());
        self::assertSame('Ajeno', $pdo->query('SELECT content FROM articles WHERE id=2')->fetchColumn());
        try { $model->trashContent(2, 'r2', ['deleted_at' => null]); self::fail('Lifecycle scope overridden'); }
        catch (\InvalidArgumentException) { self::assertCount(1, $model->activeContent(['tenant_id' => 8])->get()); }
        $engine = getenv('GFRAME_TRASH_TEST_ENGINE') === 'mysql' ? 'mysql' : 'sqlite';
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-revisions/database/' . $engine . '.sql'));
        $history = (new TrashHistoryArticle())->onConnection('trash');
        $saved = $history->saveWithHistory(2, 'r2', ['content' => 'Con historial'], 8, 8, ['tenant_id' => 8, 'deleted_at' => null]);
        self::assertSame('success', $saved['status']);
        $trash = $history->trashContent(2, $saved['data']['revision'], ['tenant_id' => 8]);
        self::assertSame('content_trashed', $trash['code']);
        self::assertSame('content_conflict', $history->saveWithHistory(2, $trash['data']['revision'], ['content' => 'Bloqueado'], 8, 8, ['tenant_id' => 8, 'deleted_at' => null])['code']);
        self::assertSame('content_restored', $history->restoreContent(2, $trash['data']['revision'], ['tenant_id' => 8])['code']);
        self::assertCount(2, $history->revisions(2, 8, ['tenant_id' => 8])['data']);
    }

    #[RunInSeparateProcess]
    public function testTwoProcessesCompeteForOneTrashRevision(): void
    {
        $root = sys_get_temp_dir() . '/gframe-trash-race-' . bin2hex(random_bytes(5)); mkdir($root);
        $pdo = new \PDO('sqlite:' . $root . '/data.sqlite');
        $pdo->exec("CREATE TABLE articles(id INTEGER PRIMARY KEY,content TEXT,revision TEXT,deleted_at TEXT);INSERT INTO articles VALUES(1,'Original','r1',NULL)");
        try {
            $children = [];
            for ($i = 0; $i < 2; $i++) {
                $code = 'require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';require ' . var_export(__FILE__, true) . ';'
                    . 'define("DB_DEFAULT_CONNECTION","trash");define("DB_CONNECTIONS",["trash"=>["driver"=>"sqlite","path"=>' . var_export($root . '/data.sqlite', true) . ']]);'
                    . '\DatabaseManager::connection()->exec("PRAGMA busy_timeout=5000");while(!is_file(' . var_export($root . '/go', true) . '))usleep(1000);'
                    . 'echo json_encode((new \GFrame\Tests\TrashArticle())->trashContent(1,"r1"));';
                $child = proc_open([PHP_BINARY, '-r', $code], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
                self::assertIsResource($child); fclose($pipes[0]); $children[] = [$child,$pipes];
            }
            touch($root . '/go'); $results = [];
            foreach ($children as [$child,$pipes]) { $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($child),$error);$results[]=json_decode($output,true,flags:JSON_THROW_ON_ERROR)['status']; }
            sort($results);self::assertSame(['error','success'],$results);
            self::assertSame('Original', $pdo->query('SELECT content FROM articles')->fetchColumn());
        } finally {unset($pdo);foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);}
    }
}

final class TrashArticle extends VersionedContentModel
{
    use ContentTrash;
    protected $table = 'articles';
    protected $fillable = ['content', 'revision', 'deleted_at'];
    public bool $failCleanup = false;
    protected function deleteTrashedRelations(array $row): void
    {
        self::queryTable('article_links')->onConnection($this->resolveCurrentConnectionName())->where('article_id', '=', $row['id'])->deleteWhere();
        if ($this->failCleanup) throw new \RuntimeException('cleanup_failed');
    }
}

final class TrashHistoryArticle extends RevisionedContentModel
{
    use ContentTrash;
    protected $table = 'articles';
    protected $fillable = ['content', 'revision', 'deleted_at'];
    protected function historyFields(): array { return ['content']; }
}
