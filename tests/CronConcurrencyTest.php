<?php

namespace GFrame\Tests;

use GFrame\Cron\CronRuntime;
use GFrame\Cron\VirtualCron;
use GFrame\Config\ConfigRepository;
use GFrame\Install\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class CronConcurrencyTest extends TestCase
{
    private string $directory;
    private PDO $database;
    private array $configuration;
    private array $previousConfig;
    private array $previousEnvironment;
    private array $children = [];
    private string $driver = 'sqlite';
    private ?string $schema = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/gframe-cron-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->configuration = ['driver' => 'sqlite', 'path' => $this->directory . '/tasks.sqlite'];
        file_put_contents($this->directory . '/connection.json', json_encode($this->configuration));
        $this->database = new PDO('sqlite:' . $this->configuration['path'], options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (getenv('GFRAME_CRON_TEST_ENGINE') === 'mysql') {
            $dsn = getenv('GFRAME_TEST_MYSQL_DSN');
            if (!$dsn) self::markTestSkipped('Se requiere GFRAME_TEST_MYSQL_DSN para el perfil MySQL.');
            $this->driver = 'mysql';
            $this->schema = 'gframe_cron_test_' . bin2hex(random_bytes(6));
            $this->database = new PDO($dsn, getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->database->exec('CREATE DATABASE `' . $this->schema . '`');
            $this->database->exec('USE `' . $this->schema . '`');
            $parts = [];
            parse_str(str_replace(';', '&', substr($dsn, 6)), $parts);
            $this->configuration = ['driver' => 'mysql', 'host' => $parts['host'] ?? '127.0.0.1', 'port' => (int)($parts['port'] ?? 3306), 'database' => $this->schema, 'username' => getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', 'password' => getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: ''];
            file_put_contents($this->directory . '/connection.json', json_encode($this->configuration));
        }
        $this->database->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/' . $this->driver . '.sql'));
        $this->database->exec('CREATE TABLE cron_effects (effect INTEGER)');
        $this->previousConfig = ConfigRepository::get();
        $this->previousEnvironment = $_ENV;
        unset($_ENV['GFRAME_CRON_MODE'], $_ENV['GFRAME_CRON_MIN_INTERVAL'], $_ENV['GFRAME_CRON_BATCH_LIMIT']);
        ConfigRepository::replace(['cron' => ['mode' => 'virtual', 'min_interval' => 60, 'batch_limit' => 2]]);
    }

    protected function tearDown(): void
    {
        foreach ($this->children as $child) {
            if (is_resource($child)) { proc_terminate($child); proc_close($child); }
        }
        ConfigRepository::replace($this->previousConfig);
        $_ENV = $this->previousEnvironment;
        if ($this->schema !== null) $this->database->exec('DROP DATABASE `' . $this->schema . '`');
        unset($this->database);
        foreach (glob($this->directory . '/*') ?: [] as $file) unlink($file);
        rmdir($this->directory);
    }

    private function spawn(string $command, ?string $barrier = null): array
    {
        $args = [PHP_BINARY, __DIR__ . '/fixtures/cron-process.php', $this->directory . '/connection.json', $command];
        if ($barrier !== null) $args[] = $barrier;
        $process = proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $this->children[] = $process;
        return [$process, $pipes];
    }

    private function childResult(array $child, int $exit = 0): array
    {
        [$process, $pipes] = $child;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame($exit, proc_close($process), $error . $output);
        return $output === '' ? [] : json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    private function task(string $key, array $payload = []): void
    {
        $statement = $this->database->prepare('INSERT INTO cron_tasks (task_key, handler_class, payload_json, scheduled_at) VALUES (?, ?, ?, ?)');
        $statement->execute([$key, 'CronProcessHandler', json_encode($payload), gmdate('Y-m-d H:i:s', time() - 10)]);
    }

    public function testTenConcurrentLaunchCompetitionsHaveExactlyOneWinnerEach(): void
    {
        for ($turn = 0; $turn < 10; $turn++) {
            $this->database->exec('UPDATE cron_runtime SET next_launch_at = 0, pending_until = 0');
            $barrier = $this->directory . '/go-' . $turn;
            $a = $this->spawn('reserve', $barrier);
            $b = $this->spawn('reserve', $barrier);
            touch($barrier);
            $results = [$this->childResult($a), $this->childResult($b)];
            self::assertCount(1, array_filter($results, static fn(array $result): bool => $result['token'] !== null));
        }
    }

    public function testLiveLongHandlerCannotBeRecoveredByAnotherRunnerAndBatchIsBounded(): void
    {
        $this->task('long', ['sleep' => 1500, 'old_lock' => true]);
        $this->task('next');
        $this->task('third');
        $owner = $this->spawn('1');
        $deadline = microtime(true) + 8;
        while ((int)$this->database->query('SELECT COUNT(*) FROM cron_effects')->fetchColumn() === 0 && microtime(true) < $deadline) usleep(10000);
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM cron_effects')->fetchColumn());
        $contender = $this->childResult($this->spawn('2'));
        self::assertTrue($contender['data']['busy']);
        self::assertSame(1, $this->childResult($owner)['data']['processed']);
        self::assertSame(2, $this->childResult($this->spawn('2'))['data']['processed']);
        self::assertSame(3, (int)$this->database->query('SELECT COUNT(*) FROM cron_effects')->fetchColumn());
    }

    public function testProcessCrashReleasesLockAndRecoversAnOrphanedReservation(): void
    {
        $this->task('crash', ['crash' => true]);
        $this->childResult($this->spawn('1'), 23);
        self::assertSame('processing', $this->database->query('SELECT status FROM cron_tasks')->fetchColumn());
        $this->database->exec("UPDATE cron_tasks SET payload_json = '{}' WHERE task_key = 'crash'");
        $result = $this->childResult($this->spawn('1'));
        self::assertSame(1, $result['data']['recovered']);
        self::assertSame(1, $result['data']['processed']);
        // El efecto anterior al fallo puede repetirse: no hay garantía externa exactamente una vez.
        self::assertSame(2, (int)$this->database->query('SELECT COUNT(*) FROM cron_effects')->fetchColumn());
    }

    public function testIntervalLaunchFailureAndObsoleteTickets(): void
    {
        $runtime = new CronRuntime($this->database);
        $calls = 0;
        self::assertSame('cron_launched', VirtualCron::kick($runtime, static function () use (&$calls): void { $calls++; })['code']);
        self::assertSame('cron_skipped', VirtualCron::kick(new CronRuntime($this->database), static function () use (&$calls): void { $calls++; })['code']);
        self::assertSame(1, $calls);
        $old = $this->database->query('SELECT launch_token FROM cron_runtime')->fetchColumn();
        $this->database->exec('UPDATE cron_runtime SET next_launch_at = 0, pending_until = 0');
        $new = $runtime->reserveLaunch(60, time());
        self::assertNotSame($old, $new);
        $runtime->abandonLaunch($old, 'stale');
        self::assertSame($new, $this->database->query('SELECT launch_token FROM cron_runtime')->fetchColumn());
        self::assertTrue($runtime->acquire());
        try { self::assertFalse($runtime->start($old)); } finally { $runtime->release(); }
        $this->database->exec('UPDATE cron_runtime SET next_launch_at = 0, pending_until = 0');
        self::assertSame('cron_unavailable', VirtualCron::kick($runtime, static function (): void { throw new \RuntimeException('test_launch_failure'); })['code']);
        self::assertNull($this->database->query('SELECT launch_token FROM cron_runtime')->fetchColumn());
        self::assertSame('test_launch_failure', $this->database->query('SELECT last_error FROM cron_runtime')->fetchColumn());
    }

    public function testRealModeAndInvalidSettingsNeverInvokeLauncher(): void
    {
        $calls = 0;
        ConfigRepository::merge(['cron' => ['mode' => 'real']]);
        self::assertSame('cron_real', VirtualCron::kick(null, static function () use (&$calls): void { $calls++; })['code']);
        $_ENV['GFRAME_CRON_MODE'] = 'invalid';
        self::assertSame('cron_unavailable', VirtualCron::kick(null, static function () use (&$calls): void { $calls++; })['code']);
        $_ENV['GFRAME_CRON_MODE'] = 'virtual';
        foreach ([0, 101, 'unlimited'] as $limit) {
            $_ENV['GFRAME_CRON_BATCH_LIMIT'] = $limit;
            self::assertSame('cron_unavailable', VirtualCron::kick(null, static function () use (&$calls): void { $calls++; })['code']);
        }
        self::assertSame(0, $calls);
    }

    public function testMigrationPreservesExistingTasksAndIsRepeatable(): void
    {
        $this->task('existing');
        $runner = MigrationRunner::frameworkDefault();
        $runner->baseline($this->database, $this->driver, ['cron-runner']);
        $this->database->exec("DELETE FROM gframe_migrations WHERE migration LIKE '%202610150001_cron_runtime.sql'");
        $this->database->exec('DROP TABLE cron_runtime');
        $runner->migrate($this->database, $this->driver, ['cron-runner']);
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM cron_tasks')->fetchColumn());
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM cron_runtime')->fetchColumn());
        self::assertSame(0, $runner->migrate($this->database, $this->driver, ['cron-runner'])['count']);
    }

    public function testAddingCronToAnExistingSiteCreatesItsEntireSchema(): void
    {
        $this->database->exec('DROP TABLE cron_tasks');
        $this->database->exec('DROP TABLE cron_runtime');
        $runner = MigrationRunner::frameworkDefault();
        self::assertSame(3, $runner->migrate($this->database, $this->driver, ['cron-runner'])['count']);
        self::assertSame(0, (int)$this->database->query('SELECT COUNT(*) FROM cron_tasks')->fetchColumn());
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM cron_runtime')->fetchColumn());
    }

    public function testRealRunnerInvalidatesPendingVirtualTicketAndLockIsNotReentrant(): void
    {
        $runtime = new CronRuntime($this->database);
        self::assertTrue($runtime->acquire());
        try { self::assertFalse((new CronRuntime($this->database))->acquire()); } finally { $runtime->release(); }
        $token = $runtime->reserveLaunch(60);
        $this->task('once');
        self::assertSame(1, $this->childResult($this->spawn('1'))['data']['processed']);
        self::assertTrue($this->childResult($this->spawn('virtual:' . $token))['data']['skipped']);
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM cron_effects')->fetchColumn());
    }

    public function testManuallyCreatedCronTablesWithoutLedgerUpgradeWithoutLosingTasks(): void
    {
        $this->task('manual-existing');
        $this->database->exec('DROP TABLE cron_runtime');
        self::assertSame(3, MigrationRunner::frameworkDefault()->migrate($this->database, $this->driver, ['cron-runner'])['count']);
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM cron_tasks')->fetchColumn());
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM cron_runtime')->fetchColumn());
    }
}
