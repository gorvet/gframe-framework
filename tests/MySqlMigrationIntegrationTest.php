<?php

namespace GFrame\Tests;

use GFrame\Install\MigrationRunner;
use GFrame\Install\SqlStatementParser;
use PDO;
use PHPUnit\Framework\TestCase;

final class MySqlMigrationIntegrationTest extends TestCase
{
    private PDO $pdo;
    private string $schema;

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
}
