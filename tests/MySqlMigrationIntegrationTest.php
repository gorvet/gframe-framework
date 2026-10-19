<?php

namespace GFrame\Tests;

use GFrame\Install\MigrationRunner;
use GFrame\Install\SqlStatementParser;
use GFrame\Modules\ModuleCatalog;
use PDO;
use PHPUnit\Framework\TestCase;

final class MySqlMigrationIntegrationTest extends TestCase
{
    private PDO $pdo;
    private string $schema;
    private ?string $fixture = null;

    protected function setUp(): void
    {
        $dsn = getenv('GFRAME_TEST_MYSQL_DSN');
        if (!$dsn) self::markTestSkipped('Se requiere GFRAME_TEST_MYSQL_DSN en un servidor de pruebas.');
        $this->pdo = new PDO($dsn, getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->schema = 'gframe_audit_' . bin2hex(random_bytes(8));
        $this->pdo->exec('CREATE DATABASE `' . $this->schema . '` CHARACTER SET utf8mb4');
        $this->pdo->exec('USE `' . $this->schema . '`');
    }

    protected function tearDown(): void
    {
        if (isset($this->schema)) $this->pdo->exec('DROP DATABASE `' . $this->schema . '`');
        if ($this->fixture !== null) {
            unlink($this->fixture . '/journal-test/migration.sql');
            unlink($this->fixture . '/journal-test/module.php');
            rmdir($this->fixture . '/journal-test');
            rmdir($this->fixture);
        }
    }

    public function testLegacyAuthUpgradeWithImplicitDdlCommitsAndOverlappingColumn(): void
    {
        $this->legacyAuth();
        $runner = MigrationRunner::frameworkDefault();
        self::assertSame(3, $runner->migrate($this->pdo, 'mysql', ['auth-ui'])['count']);
        self::assertSame(0, $runner->migrate($this->pdo, 'mysql', ['auth-ui'])['count']);
        self::assertFalse($this->pdo->inTransaction());
        self::assertNotFalse($this->pdo->query("SHOW COLUMNS FROM gframe_sessions LIKE 'tenant_role_version'")->fetch());
    }

    public function testExistingSessionsAndPartiallyAppliedAdditiveMigration(): void
    {
        $this->legacyAuth();
        $sql = file_get_contents(dirname(__DIR__) . '/resources/modules/auth-ui/database/migrations/mysql/202609290001_managed_sessions.sql');
        foreach ((new SqlStatementParser())->parse($sql) as $statement) $this->pdo->exec($statement);
        $this->pdo->exec('ALTER TABLE users ADD COLUMN authorization_version BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER force_password_change');
        self::assertSame(3, MigrationRunner::frameworkDefault()->migrate($this->pdo, 'mysql', ['auth-ui'])['count']);
    }

    public function testFreshAuthSchemaBaselinesWithoutReapplyingMigrations(): void
    {
        $schema = file_get_contents(dirname(__DIR__) . '/resources/database/schema/mysql/auth.sql');
        foreach ((new SqlStatementParser())->parse($schema) as $statement) $this->pdo->exec($statement);
        $runner = MigrationRunner::frameworkDefault();
        self::assertSame(3, $runner->baseline($this->pdo, 'mysql', ['auth-ui'])['count']);
        self::assertSame(0, $runner->migrate($this->pdo, 'mysql', ['auth-ui'])['count']);
    }

    private function legacyAuth(): void
    {
        $this->pdo->exec('CREATE TABLE users (user_id BIGINT UNSIGNED PRIMARY KEY, force_password_change TINYINT NOT NULL DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE roles (role_id BIGINT UNSIGNED PRIMARY KEY, is_system TINYINT NOT NULL DEFAULT 0)');
    }

    public function testJournalResumesConfirmedStatementsAndBlocksUncertainReplay(): void
    {
        $runner = $this->journalRunner();
        try {
            $runner->migrate($this->pdo, 'mysql', ['journal-test']);
            self::fail('La tercera sentencia debe fallar.');
        } catch (\PDOException $exception) {
            self::assertStringContainsString('missing_table', $exception->getMessage());
        }
        self::assertSame(['done', 'done', 'started'], $this->pdo->query('SELECT status FROM gframe_migration_statements ORDER BY statement_no')->fetchAll(PDO::FETCH_COLUMN));
        try {
            $runner->migrate($this->pdo, 'mysql', ['journal-test']);
            self::fail('No debe repetirse una sentencia incierta.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Resultado incierto', $exception->getMessage());
        }
        $this->pdo->exec('CREATE TABLE missing_table (value INT)');
        $runner->resolveInterruptedStatement($this->pdo, 'journal-test:migration.sql', 3, false);
        self::assertSame(1, $runner->migrate($this->pdo, 'mysql', ['journal-test'])['count']);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM journal_values')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM missing_table')->fetchColumn());
        // Emulate a crash after SQL succeeded but before the journal completion write.
        $this->pdo->exec('DELETE FROM gframe_migrations');
        $this->pdo->exec("UPDATE gframe_migration_statements SET status = 'started' WHERE statement_no = 3");
        $runner->resolveInterruptedStatement($this->pdo, 'journal-test:migration.sql', 3, true);
        self::assertSame(1, $runner->migrate($this->pdo, 'mysql', ['journal-test'])['count']);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM missing_table')->fetchColumn());
        self::assertSame(0, $runner->migrate($this->pdo, 'mysql', ['journal-test'])['count']);
    }

    public function testJournalRejectsChangedMigrationAndConcurrentOwner(): void
    {
        $runner = $this->journalRunner();
        try { $runner->migrate($this->pdo, 'mysql', ['journal-test']); } catch (\PDOException $exception) {}
        file_put_contents($this->fixture . '/journal-test/migration.sql', "\nINSERT INTO journal_values VALUES (99);", FILE_APPEND);
        try {
            $runner->migrate($this->pdo, 'mysql', ['journal-test']);
            self::fail('Debe rechazar una migración modificada.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('cambió', $exception->getMessage());
        }
        file_put_contents($this->fixture . '/journal-test/migration.sql', '');
        try {
            $runner->migrate($this->pdo, 'mysql', ['journal-test']);
            self::fail('Debe rechazar incluso una migración vaciada.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('cambió', $exception->getMessage());
        }
        $other = new PDO(getenv('GFRAME_TEST_MYSQL_DSN'), getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $other->exec('USE `' . $this->schema . '`');
        $lock = 'gframe.migrate.' . sha1($this->schema);
        $query = $other->prepare('SELECT GET_LOCK(?, 0)');
        $query->execute([$lock]);
        self::assertSame(1, (int)$query->fetchColumn());
        try {
            $runner->migrate($this->pdo, 'mysql', ['journal-test']);
            self::fail('Debe rechazar la ejecución concurrente.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Otra conexión', $exception->getMessage());
        } finally {
            $release = $other->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lock]);
        }
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM journal_values')->fetchColumn());
    }

    public function testCronMigrationRejectsIncompatibleExistingIntegerDefault(): void
    {
        $this->pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/mysql.sql'));
        $this->pdo->exec('ALTER TABLE cron_tasks ALTER COLUMN attempts SET DEFAULT 7');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cron_tasks.attempts existe con una definición incompatible');
        MigrationRunner::frameworkDefault()->migrate($this->pdo, 'mysql', ['cron-runner']);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testContentAutosaveSchemaAndConditionalWrites(): void
    {
        $this->pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-autosave/database/mysql.sql'));
        $parts = []; parse_str(str_replace(';', '&', substr(getenv('GFRAME_TEST_MYSQL_DSN'), 6)), $parts);
        define('DB_CONNECTIONS', ['autosave_mysql' => ['driver' => 'mysql', 'host' => $parts['host'] ?? '127.0.0.1', 'port' => (int)($parts['port'] ?? 3306),
            'database' => $this->schema, 'username' => getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', 'password' => getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '']]);
        $service = new \GFrame\Content\ContentDraftService((new \GFrame\Content\ContentDraftModel())->onConnection('autosave_mysql'));
        $first = $service->save(7, 9, 'article:42:content', '<p>Privado</p>', null, 'r1', 'r1');
        self::assertSame('success', $first['status']);
        self::assertSame('autosave_conflict', $service->save(7, 9, 'article:42:content', 'stale', null, 'r1', 'r1')['code']);
        $next = $service->save(7, 9, 'article:42:content', '<p>Nuevo</p>', $first['data']['version'], 'r1', 'r1');
        self::assertSame('success', $next['status']);
        self::assertSame('autosave_conflict', $service->discard(7, 9, 'article:42:content', $first['data']['version'], 'r1', 'r1')['code']);
        self::assertFalse($service->load(8, 9, 'article:42:content')['data']['available']);
        self::assertSame('<p>Nuevo</p>', $service->load(7, 9, 'article:42:content')['data']['content']);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testContentEditingSchemaVersionedSaveAndScopedPresence(): void
    {
        $this->pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-editing/database/mysql.sql'));
        $this->pdo->exec("CREATE TABLE articles (id INTEGER PRIMARY KEY, tenant_id INTEGER, content TEXT, revision VARCHAR(128)); INSERT INTO articles VALUES (1,7,'Original','r1')");
        $parts = []; parse_str(str_replace(';', '&', substr(getenv('GFRAME_TEST_MYSQL_DSN'), 6)), $parts);
        define('DB_CONNECTIONS', ['editing_mysql' => ['driver' => 'mysql', 'host' => $parts['host'] ?? '127.0.0.1', 'port' => (int)($parts['port'] ?? 3306),
            'database' => $this->schema, 'username' => getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', 'password' => getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '']]);
        $model = (new class extends \GFrame\Content\VersionedContentModel {
            protected $table = 'articles';
            protected $fillable = ['content', 'revision'];
        })->onConnection('editing_mysql');
        self::assertSame('content_conflict', $model->useStrictComparison(false)->saveRevision(1, 'R1', ['content' => 'Wrong case'], ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->saveRevision(1, 'r1 ', ['content' => 'Trailing space'], ['tenant_id' => 7])['code']);
        self::assertSame('content_conflict', $model->saveRevision(1, 'r1', ['content' => 'Ajeno'], ['tenant_id' => 8])['code']);
        $saved = $model->saveRevision(1, 'r1', ['content' => 'Actual'], ['tenant_id' => 7]);
        self::assertSame('success', $saved['status']);
        self::assertSame('content_conflict', $model->saveRevision(1, 'r1', ['content' => 'Obsoleto'])['code']);
        self::assertSame('Actual', $this->pdo->query('SELECT content FROM articles WHERE id=1')->fetchColumn());
        $presence = new \GFrame\Content\ContentPresenceService((new \GFrame\Content\ContentPresenceModel())->onConnection('editing_mysql'));
        self::assertSame(0, $presence->touch(1, 7, 'article:1', str_repeat('a',32), 100)['data']['other_sessions']);
        self::assertSame(1, $presence->touch(2, 7, 'article:1', str_repeat('b',32), 101)['data']['other_sessions']);
        self::assertSame(0, $presence->touch(2, 8, 'article:1', str_repeat('b',32), 101)['data']['other_sessions']);
        self::assertSame(0, $presence->touch(1, 7, 'article:1', str_repeat('a',32), 191)['data']['other_sessions']);
        $this->pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/database/schema/mysql/auth.sql'));
        $runner = MigrationRunner::frameworkDefault();
        $runner->baseline($this->pdo, 'mysql', ['self-account']);
        self::assertSame(1, $runner->migrate($this->pdo, 'mysql', ['content-editing'])['count']);
        self::assertSame(0, $runner->migrate($this->pdo, 'mysql', ['content-editing'])['count']);
    }

    private function journalRunner(): MigrationRunner
    {
        $this->fixture = sys_get_temp_dir() . '/gframe-journal-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/journal-test', 0777, true);
        file_put_contents($this->fixture . '/journal-test/module.php', "<?php return ['name' => 'journal-test', 'type' => 'backend-module', 'migrations' => ['mysql' => ['migration.sql']]];");
        file_put_contents($this->fixture . '/journal-test/migration.sql', 'CREATE TABLE journal_values (value INT); INSERT INTO journal_values VALUES (1); INSERT INTO missing_table VALUES (2);');
        return new MigrationRunner(new ModuleCatalog($this->fixture));
    }
}
