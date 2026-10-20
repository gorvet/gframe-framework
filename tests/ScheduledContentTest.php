<?php
namespace GFrame\Tests;

use GFrame\Content\{ContentTrash, ScheduledContent, ScheduledPublicationCronHandler, VersionedContentModel, RevisionedContentModel};
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ScheduledContentTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testHistoryRestorePreservesCurrentPublicationStateAndDate(): void
    {
        $connection = ['driver' => 'sqlite', 'path' => ':memory:'];
        $engine = getenv('GFRAME_PUBLICATION_TEST_ENGINE') === 'mysql' ? 'mysql' : 'sqlite';
        if ($engine === 'mysql') {
            $admin = new \PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
            $schema = 'gframe_restore_test_' . bin2hex(random_bytes(6));
            $admin->exec('CREATE DATABASE `' . $schema . '`');
            register_shutdown_function(static function () use ($admin, $schema): void { $admin->exec('DROP DATABASE IF EXISTS `' . $schema . '`'); });
            $connection = ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => $schema, 'username' => 'root', 'password' => ''];
        }
        define('DB_DEFAULT_CONNECTION', 'unused');
        define('DB_CONNECTIONS', ['publication' => $connection]);
        $pdo = \DatabaseManager::connection('publication');
        $pdo->exec('CREATE TABLE articles(id INTEGER PRIMARY KEY,tenant_id INTEGER,content TEXT,revision VARCHAR(128),status VARCHAR(32),publish_at DATETIME NULL,deleted_at DATETIME NULL)');
        $pdo->exec("INSERT INTO articles VALUES(1,7,'Publicado original','r1','published',NULL,NULL)");
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-revisions/database/' . $engine . '.sql'));
        $model = (new PublicationHistoryArticle())->onConnection('publication');
        $scope = ['tenant_id' => 7];
        $date = '2099-01-01 00:00:00';
        $revision = $model->schedulePublication(1, 'r1', $date, 7, 7, $scope)['data']['revision'];
        $publishedID = (int)$pdo->query("SELECT id FROM content_revisions WHERE revision='r1'")->fetchColumn();
        $scheduledID = (int)$pdo->query("SELECT id FROM content_revisions WHERE revision=" . $pdo->quote($revision))->fetchColumn();
        self::assertGreaterThan(0, $publishedID);
        self::assertGreaterThan(0, $scheduledID);
        $revision = $model->saveWithHistory(1, $revision, ['content' => 'Edición nueva'], 7, 7, $scope)['data']['revision'];
        self::assertSame('content_conflict', $model->restoreRevision(1, $publishedID, 'r1', 7, 7, $scope)['code']);
        self::assertSame('content_conflict', $model->restoreRevision(1, $publishedID, $revision, 8, 8, ['tenant_id' => 8])['code']);
        $restored = $model->restoreRevision(1, $publishedID, $revision, 7, 7, $scope);
        self::assertSame('content_saved', $restored['code']);
        self::assertNotSame($revision, $restored['data']['revision']);
        $row = $pdo->query('SELECT * FROM articles WHERE id=1')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('Publicado original', $row['content']);
        self::assertSame('scheduled', $row['status']);
        self::assertSame($date, $row['publish_at']);
        $revision = $model->cancelPublication(1, $restored['data']['revision'], 7, 7, $scope)['data']['revision'];
        $restored = $model->restoreRevision(1, $scheduledID, $revision, 7, 7, $scope);
        self::assertSame('content_saved', $restored['code']);
        $row = $pdo->query('SELECT * FROM articles WHERE id=1')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('draft', $row['status']);
        self::assertNull($row['publish_at']);
        self::assertSame([], $model->dueContent($scope, new \DateTimeImmutable('2100-01-01'))->get());
        $snapshot = json_decode($pdo->query('SELECT fields_json FROM content_revisions WHERE revision=' . $pdo->quote($restored['data']['revision']))->fetchColumn(), true);
        self::assertSame('draft', $snapshot['status']);
        self::assertNull($snapshot['publish_at']);
        $revision = $restored['data']['revision'];
        $beforeFailure = $pdo->query('SELECT * FROM articles WHERE id=1')->fetch(\PDO::FETCH_ASSOC);
        $historyCount = (int)$pdo->query('SELECT COUNT(*) FROM content_revisions')->fetchColumn();
        $pdo->exec($engine === 'mysql'
            ? "CREATE TRIGGER audit_history_failure BEFORE INSERT ON content_revisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit history failure'"
            : "CREATE TRIGGER audit_history_failure BEFORE INSERT ON content_revisions BEGIN SELECT RAISE(ABORT, 'audit history failure'); END");
        try {
            $model->restoreRevision(1, $publishedID, $revision, 7, 7, $scope);
            self::fail('El fallo al guardar el historial debe revertir la restauración.');
        } catch (\PDOException) {
            self::assertSame($beforeFailure, $pdo->query('SELECT * FROM articles WHERE id=1')->fetch(\PDO::FETCH_ASSOC));
            self::assertSame($historyCount, (int)$pdo->query('SELECT COUNT(*) FROM content_revisions')->fetchColumn());
        }
        $pdo->exec('DROP TRIGGER audit_history_failure');
        $pdo->exec('CREATE TABLE custom_articles(id INTEGER PRIMARY KEY,content TEXT,revision VARCHAR(128),state VARCHAR(32),release_at DATETIME NULL)');
        $pdo->exec("INSERT INTO custom_articles VALUES(1,'Original','custom-r1','live',NULL)");
        $custom = (new PublicationCustomHistoryArticle())->onConnection('publication');
        $scheduled = $custom->schedulePublication(1, 'custom-r1', $date, 7);
        $entryID = (int)$pdo->query("SELECT id FROM content_revisions WHERE revision='custom-r1'")->fetchColumn();
        $restored = $custom->restoreRevision(1, $entryID, $scheduled['data']['revision'], 7);
        self::assertSame('content_saved', $restored['code']);
        $row = $pdo->query('SELECT * FROM custom_articles WHERE id=1')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('queued', $row['state']);
        self::assertSame($date, $row['release_at']);
        $published = $custom->publishScheduled(1, $restored['data']['revision'], 7, null, [], new \DateTimeImmutable('2100-01-01'));
        $restored = $custom->restoreRevision(1, $entryID, $published['data']['revision'], 7);
        self::assertSame('content_saved', $restored['code']);
        self::assertSame('live', $pdo->query('SELECT state FROM custom_articles WHERE id=1')->fetchColumn());
        self::assertNull($pdo->query('SELECT release_at FROM custom_articles WHERE id=1')->fetchColumn());
    }

    #[RunInSeparateProcess]
    public function testSchedulingCancellationPublicationAndHistory(): void
    {
        $connection = ['driver' => 'sqlite', 'path' => ':memory:'];
        $engine = getenv('GFRAME_PUBLICATION_TEST_ENGINE') === 'mysql' ? 'mysql' : 'sqlite';
        if ($engine === 'mysql') {
            $admin = new \PDO('mysql:host=127.0.0.1;port=3306', 'root', ''); $schema = 'gframe_publication_test_' . bin2hex(random_bytes(6));
            $admin->exec('CREATE DATABASE `' . $schema . '`');
            register_shutdown_function(static function () use ($admin, $schema): void { $admin->exec('DROP DATABASE IF EXISTS `' . $schema . '`'); });
            $connection = ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => $schema, 'username' => 'root', 'password' => ''];
        }
        define('DB_DEFAULT_CONNECTION', 'unused');define('DB_CONNECTIONS', ['publication' => $connection]);
        $pdo = \DatabaseManager::connection('publication');
        $pdo->exec('CREATE TABLE articles(id INTEGER PRIMARY KEY,tenant_id INTEGER,content TEXT,revision VARCHAR(128),status VARCHAR(32),publish_at DATETIME NULL,deleted_at DATETIME NULL)');
        $pdo->exec("INSERT INTO articles VALUES(1,7,'Original','R1','draft',NULL,NULL),(2,8,'Ajeno','r2','draft',NULL,NULL)");
        $model = (new PublicationArticle())->onConnection('publication');$scope = ['tenant_id' => 7];$future = '2099-01-01 00:00:00';$now = new \DateTimeImmutable('2100-01-01', new \DateTimeZone('UTC'));
        self::assertSame('content_conflict', $model->schedulePublication(1, 'r1', $future, 7, 7, $scope)['code']);
        self::assertSame('content_conflict', $model->schedulePublication(1, 'R1 ', $future, 7, 7, $scope)['code']);
        self::assertSame('content_conflict', $model->schedulePublication(1, 'R1', $future, 7, 8, ['tenant_id' => 8])['code']);
        foreach (['2099-02-30 00:00:00', '2020-01-01 00:00:00'] as $invalid) {
            try { $model->schedulePublication(1, 'R1', $invalid, 7, 7, $scope);self::fail('Invalid date accepted'); } catch (\InvalidArgumentException) {self::assertSame('R1', $pdo->query('SELECT revision FROM articles WHERE id=1')->fetchColumn());}
        }
        $scheduled = $model->schedulePublication(1, 'R1', $future, 7, 7, $scope);$revision = $scheduled['data']['revision'];
        self::assertSame('success', $scheduled['status']);self::assertNotSame('R1', $revision);
        self::assertSame([], $model->dueContent($scope)->get());
        self::assertSame('content_conflict', $model->publishScheduled(1, $revision, 7, 7, $scope)['code']);
        $cancelled = $model->cancelPublication(1, $revision, 7, 7, $scope);self::assertSame('success', $cancelled['status']);
        self::assertSame('draft', $pdo->query('SELECT status FROM articles WHERE id=1')->fetchColumn());
        self::assertSame('content_conflict', $model->publishScheduled(1, $revision, 7, 7, $scope, $now)['code']);
        $revision = $model->schedulePublication(1, $cancelled['data']['revision'], $future, 7, 7, $scope)['data']['revision'];
        $old = $revision;$revision = $model->schedulePublication(1, $revision, '2099-02-01 00:00:00', 7, 7, $scope)['data']['revision'];
        self::assertSame('content_conflict', $model->publishScheduled(1, $old, 7, 7, $scope, $now)['code']);
        self::assertCount(1, $model->dueContent($scope, $now)->get());
        self::assertSame([], $model->dueContent(['tenant_id' => 8], $now)->get());
        $published = $model->publishScheduled(1, $revision, 7, 7, $scope, $now);
        self::assertSame('success', $published['status']);self::assertSame('published', $pdo->query('SELECT status FROM articles WHERE id=1')->fetchColumn());
        self::assertSame(null, $pdo->query('SELECT publish_at FROM articles WHERE id=1')->fetchColumn());
        self::assertSame('content_conflict', $model->publishScheduled(1, $revision, 7, 7, $scope, $now)['code']);
        $revision = $model->schedulePublication(1, $published['data']['revision'], $future, 7, 7, $scope)['data']['revision'];
        $trashed = $model->trashContent(1, $revision, $scope);
        self::assertSame([], $model->dueContent($scope, $now)->get());
        self::assertSame('content_conflict', $model->publishScheduled(1, $trashed['data']['revision'], 7, 7, $scope, $now)['code']);
        try { $model->where('id', '=', 2)->orWhere('id', '=', 1)->dueContent($scope);self::fail('Pending OR accepted'); } catch (\LogicException) {self::assertTrue(true);}$model->reset();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-revisions/database/' . $engine . '.sql'));
        $history = (new PublicationHistoryArticle())->onConnection('publication');
        $revision = $history->schedulePublication(2, 'r2', $future, 8, 8, ['tenant_id' => 8])['data']['revision'];
        self::assertSame('success', $history->publishScheduled(2, $revision, 8, 8, ['tenant_id' => 8], $now)['status']);
        self::assertCount(3, $history->revisions(2, 8, ['tenant_id' => 8])['data']);
        self::assertSame('Ajeno', $pdo->query('SELECT content FROM articles WHERE id=2')->fetchColumn());
        $revision = $history->schedulePublication(2, $pdo->query('SELECT revision FROM articles WHERE id=2')->fetchColumn(), $future, 8, 8, ['tenant_id' => 8])['data']['revision'];
        $pdo->exec('DROP TABLE content_revisions');
        try { $history->publishScheduled(2, $revision, 8, 8, ['tenant_id' => 8], $now);self::fail('History failure ignored'); } catch (\PDOException) {
            self::assertSame('scheduled', $pdo->query('SELECT status FROM articles WHERE id=2')->fetchColumn());self::assertSame($revision, $pdo->query('SELECT revision FROM articles WHERE id=2')->fetchColumn());
        }
        // Actual cron repository/runtime integration, not a browser session or direct callback alone.
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/' . $engine . '.sql'));
        $pdo->exec("UPDATE articles SET status='scheduled',publish_at='2020-01-01 00:00:00' WHERE id=2");
        $repository = new PublicationCronRepository();
        $task = (new \CronTaskService($repository))->schedule('publication.test', PublicationTestHandler::class, '2020-01-01 00:00:00', [], 60);
        self::assertSame('success', $task['status']);
        $result = (new \CronScheduler($repository, new \GFrame\Cron\CronRuntime($pdo)))->runDue();
        self::assertSame(0, $result['data']['failed']);self::assertSame(1, $result['data']['processed']);
        self::assertSame('published', $pdo->query('SELECT status FROM articles WHERE id=2')->fetchColumn());
        self::assertSame('pending', $repository->findByKey('publication.test')['status']);
        self::assertSame(['published' => 0, 'skipped' => 0], (new PublicationTestHandler())->handle()['data']);
        $pdo->exec("UPDATE articles SET status='scheduled',publish_at='2020-01-01 00:00:00' WHERE id=2");
        PublicationTestHandler::$allowed = false;
        self::assertSame(['published' => 0, 'skipped' => 1], (new PublicationTestHandler())->handle(['tenant_id' => 7])['data']);
        self::assertSame('scheduled', $pdo->query('SELECT status FROM articles WHERE id=2')->fetchColumn());
        PublicationTestHandler::$allowed = true;
    }

    #[RunInSeparateProcess]
    public function testTwoWorkersPublishOnce(): void
    {
        $root = sys_get_temp_dir() . '/gframe-publication-race-' . bin2hex(random_bytes(5));mkdir($root);
        $pdo = new \PDO('sqlite:' . $root . '/data.sqlite');
        $pdo->exec("CREATE TABLE articles(id INTEGER PRIMARY KEY,content TEXT,revision TEXT,status TEXT,publish_at TEXT,deleted_at TEXT);INSERT INTO articles VALUES(1,'Original','r1','scheduled','2020-01-01 00:00:00',NULL)");
        try {
            $children = [];
            for ($i = 0; $i < 2; $i++) {
                $code = 'require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';require ' . var_export(__FILE__, true) . ';'
                    . 'define("DB_DEFAULT_CONNECTION","publication");define("DB_CONNECTIONS",["publication"=>["driver"=>"sqlite","path"=>' . var_export($root . '/data.sqlite', true) . ']]);'
                    . '\DatabaseManager::connection()->exec("PRAGMA busy_timeout=5000");while(!is_file(' . var_export($root . '/go', true) . '))usleep(1000);'
                    . 'echo json_encode((new \GFrame\Tests\PublicationArticle())->publishScheduled(1,"r1",7));';
                $child = proc_open([PHP_BINARY, '-r', $code], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
                self::assertIsResource($child);fclose($pipes[0]);$children[] = [$child,$pipes];
            }
            touch($root . '/go');$results = [];
            foreach ($children as [$child,$pipes]) { $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($child),$error);$results[]=json_decode($output,true,flags:JSON_THROW_ON_ERROR)['status']; }
            sort($results);self::assertSame(['error','success'],$results);self::assertSame('published', $pdo->query('SELECT status FROM articles')->fetchColumn());
        } finally {unset($pdo);foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);}
    }
}

final class PublicationCustomHistoryArticle extends RevisionedContentModel
{
    use ScheduledContent;
    protected $table = 'custom_articles';
    protected $fillable = ['content', 'revision', 'state', 'release_at'];
    protected function historyFields(): array { return ['content', 'state', 'release_at']; }
    protected function publicationStatusColumn(): string { return 'state'; }
    protected function publicationDateColumn(): string { return 'release_at'; }
    protected function scheduledPublicationStatus(): string { return 'queued'; }
    protected function publishedPublicationStatus(): string { return 'live'; }
}

class PublicationArticle extends VersionedContentModel
{
    use ScheduledContent, ContentTrash;
    protected $table = 'articles';
    protected $fillable = ['content', 'status', 'publish_at', 'revision', 'deleted_at'];
}
final class PublicationHistoryArticle extends RevisionedContentModel
{
    use ScheduledContent, ContentTrash;
    protected $table = 'articles';
    protected $fillable = ['content', 'status', 'publish_at', 'revision', 'deleted_at'];
    protected function historyFields(): array { return ['content', 'status', 'publish_at']; }
}
final class PublicationTestHandler extends ScheduledPublicationCronHandler
{
    public static bool $allowed = true;
    protected function publicationModel(): VersionedContentModel { return (new PublicationArticle())->onConnection('publication'); }
    protected function publicationContext(array $task): array { return ['scope' => ['tenant_id' => 8], 'tenant_id' => 8, 'author_id' => 8]; }
    protected function mayPublish(array $row, array $context): bool { return self::$allowed && (int)$row['tenant_id'] === $context['tenant_id']; }
}

final class PublicationCronRepository extends \CronDataProvider { protected $connection = 'publication'; }
