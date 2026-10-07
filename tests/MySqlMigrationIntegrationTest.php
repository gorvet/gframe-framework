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

    private function journalRunner(): MigrationRunner
    {
        $this->fixture = sys_get_temp_dir() . '/gframe-journal-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/journal-test', 0777, true);
        file_put_contents($this->fixture . '/journal-test/module.php', "<?php return ['name' => 'journal-test', 'type' => 'backend-module', 'migrations' => ['mysql' => ['migration.sql']]];");
        file_put_contents($this->fixture . '/journal-test/migration.sql', 'CREATE TABLE journal_values (value INT); INSERT INTO journal_values VALUES (1); INSERT INTO missing_table VALUES (2);');
        return new MigrationRunner(new ModuleCatalog($this->fixture));
    }
}
