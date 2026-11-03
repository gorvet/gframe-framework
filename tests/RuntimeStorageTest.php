<?php

namespace GFrame\Tests;

use GFrame\Install\MigrationRunner;
use GFrame\Session\DatabaseSessionHandler;
use GFrame\Storage\DatabaseStateStore;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeStorageTest extends TestCase
{
    public static function engines(): array { return [['sqlite'], ['mysql']]; }

    #[DataProvider('engines')]
    public function testAtomicStateConsumptionRollbackExpiryAndGuestSessions(string $engine): void
    {
        $schema = null;
        if ($engine === 'mysql') {
            if (!getenv('GFRAME_TEST_MYSQL_DSN')) self::markTestSkipped('Requiere GFRAME_TEST_MYSQL_DSN.');
            $database = new PDO(getenv('GFRAME_TEST_MYSQL_DSN'), getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '');
            $schema = 'gframe_state_test_' . bin2hex(random_bytes(8));
            $database->exec('CREATE DATABASE `' . $schema . '`');
            $database->exec('USE `' . $schema . '`');
        } else $database = new PDO('sqlite::memory:');
        try {
            $runner = MigrationRunner::frameworkDefault();
            self::assertSame(1, $runner->migrate($database, $engine, ['runtime-storage'])['count']);
            self::assertSame(0, $runner->migrate($database, $engine, ['runtime-storage'])['count']);
            $store = new DatabaseStateStore($database);
            $store->create('test', 'record', ['count' => 1]);
            try {
                $store->update('test', 'record', static function (array &$data): void { $data['count'] = 99; throw new \RuntimeException('rollback'); });
                self::fail('Expected rollback');
            } catch (\RuntimeException $exception) { self::assertSame('rollback', $exception->getMessage()); }
            self::assertSame(1, $store->update('test', 'record', static fn(array $data): int => $data['count'], true));
            self::assertSame(0, (int)$database->query('SELECT COUNT(*) FROM gframe_runtime_state')->fetchColumn());
            try { $store->update('test', 'record', static fn() => null, true); self::fail('Consumed jobs cannot run twice'); }
            catch (\RuntimeException $exception) { self::assertNotEmpty($exception->getMessage()); }
            $store->create('test', 'expired', [], time() - 1);
            self::assertSame(1, $store->prune());
            $database->beginTransaction();
            try { $store->create('test', 'nested', []); self::fail('Must reject caller transaction'); }
            catch (\RuntimeException) { self::assertTrue($database->inTransaction()); }
            $database->rollBack();
            // Public forms need SQL sessions without installing account/role tables.
            $sessions = new DatabaseSessionHandler($database);
            self::assertSame('', $sessions->read('new-session'));
            self::assertTrue($sessions->write('new-session', 'payload'));
            self::assertSame('payload', $sessions->read('new-session'));
            self::assertTrue($sessions->destroy('new-session'));
            self::assertSame('', $sessions->read('new-session'));
        } finally { if ($schema !== null) $database->exec('DROP DATABASE `' . $schema . '`'); }
    }

    public function testFileBackendsAreRejectedAndModuleStateHasNoFileWriters(): void
    {
        $previous = \GFrame\Config\ConfigRepository::get();
        try {
            \GFrame\Config\ConfigRepository::replace(['auth' => ['rate_limit' => ['driver' => 'file']], 'mail' => ['rate_limit' => ['driver' => 'file']]]);
            self::assertSame('auth_rate_limit_unavailable', (new \GFrame\Auth\AuthRateLimiter())->reserve('login', 'identity', 'ip')['code']);
            self::assertSame('mail_rate_limit_unavailable', (new \GFrame\Mail\MailRateLimiter())->run('scope', 'identity', static function (): array { self::fail('No send on file driver'); })['code']);
            \GFrame\Config\ConfigRepository::replace(['session' => ['driver' => 'native']]);
            try { \GFrame\Session\SessionRuntime::start(''); self::fail('Native file sessions must be rejected'); }
            catch (\RuntimeException $exception) { self::assertNotEmpty($exception->getMessage()); }
        } finally { \GFrame\Config\ConfigRepository::replace($previous); }
        foreach (['src/GFrame/Auth/AuthRateLimiter.php', 'src/GFrame/Mail/MailRateLimiter.php',
            'src/GFrame/Files/DownloadGrantStore.php', 'src/GFrame/Cron/VirtualCron.php', 'src/async/Async.php', 'bin/async-worker.php'] as $path) {
            self::assertDoesNotMatchRegularExpression('/\b(file_put_contents|fopen|ftruncate|tempnam)\s*\(/', file_get_contents(dirname(__DIR__) . '/' . $path), $path);
        }
    }
}
