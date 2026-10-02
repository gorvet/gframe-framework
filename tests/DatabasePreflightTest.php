<?php

namespace GFrame\Tests;

use GFrame\Install\DatabasePreflight;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabasePreflightTest extends TestCase
{
    public function testEmptyAndOccupiedDatabasesAreDistinguished(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $check = new DatabasePreflight();
        self::assertSame('database_empty', $check->emptyDatabase($pdo, 'sqlite')['code']);
        $pdo->exec('CREATE TABLE existing_data (id INTEGER)');
        self::assertSame('database_not_empty', $check->emptyDatabase($pdo, 'sqlite')['code']);
        self::assertSame('error', $check->emptyDatabase($pdo, 'sqlite')['status']);
    }

    public function testMissingSqliteDatabaseIsNotCreatedDuringPreflight(): void
    {
        $root = sys_get_temp_dir() . '/gframe-preflight-' . bin2hex(random_bytes(5));
        self::assertSame('database_missing', (new DatabasePreflight())->check($root, ['driver' => 'sqlite'])['code']);
        self::assertDirectoryDoesNotExist($root);
    }

    public function testExistingSqliteFileIsInspectedWithoutChangingIt(): void
    {
        $root = sys_get_temp_dir() . '/gframe-preflight-' . bin2hex(random_bytes(5));
        mkdir($root . '/storage', 0775, true);
        $path = $root . '/storage/database.sqlite';
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->exec('CREATE TABLE important_data (id INTEGER)');
            $pdo = null;
            $before = hash_file('sha256', $path);
            self::assertSame('database_not_empty', (new DatabasePreflight())->check($root, ['driver' => 'sqlite'])['code']);
            self::assertSame($before, hash_file('sha256', $path));
        } finally {
            unlink($path);
            rmdir($root . '/storage');
            rmdir($root);
        }
    }

    public function testMysqlPreflightDistinguishesMissingEmptyAndOccupied(): void
    {
        if (getenv('GFRAME_TEST_MYSQL') !== '1') self::markTestSkipped('Requiere MySQL de pruebas.');
        $settings = ['driver' => 'mysql', 'host' => getenv('GFRAME_TEST_MYSQL_HOST') ?: '127.0.0.1', 'port' => (int)(getenv('GFRAME_TEST_MYSQL_PORT') ?: 3306), 'username' => getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', 'password' => getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '', 'database' => 'gframe_preflight_test_' . bin2hex(random_bytes(6))];
        $server = new PDO('mysql:host=' . $settings['host'] . ';port=' . $settings['port'], $settings['username'], $settings['password']);
        $check = new DatabasePreflight();
        try {
            self::assertSame('database_missing', $check->check(sys_get_temp_dir(), $settings)['code']);
            $server->exec('CREATE DATABASE `' . $settings['database'] . '`');
            self::assertSame('database_empty', $check->check(sys_get_temp_dir(), $settings)['code']);
            $server->exec('CREATE TABLE `' . $settings['database'] . '`.existing_data (id INTEGER)');
            self::assertSame('database_not_empty', $check->check(sys_get_temp_dir(), $settings)['code']);
            $invalid = array_replace($settings, ['username' => 'gframe_invalid_user', 'password' => 'invalid']);
            self::assertSame('database_access_denied', $check->check(sys_get_temp_dir(), $invalid)['code']);
            $settings['port'] = 1;
            self::assertSame('database_connection_failed', $check->check(sys_get_temp_dir(), $settings)['code']);
        } finally {
            if (preg_match('/^gframe_preflight_test_[a-f0-9]{12}$/', $settings['database'])) $server->exec('DROP DATABASE IF EXISTS `' . $settings['database'] . '`');
        }
    }
}
