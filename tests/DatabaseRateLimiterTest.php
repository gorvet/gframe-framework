<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthRateLimiter;
use GFrame\Config\ConfigRepository;
use GFrame\Install\MigrationRunner;
use GFrame\Install\SchemaInstaller;
use GFrame\Mail\MailRateLimiter;
use GFrame\Mail\MailService;
use GFrame\Security\DatabaseRateLimitStore;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseRateLimiterTest extends TestCase
{
    private array $configuration;
    private array $pool;
    private ?PDO $pdo = null;
    private ?string $schema = null;
    private int $now = 1000;

    protected function setUp(): void
    {
        $this->configuration = ConfigRepository::get();
        $this->pool = (new \ReflectionProperty(\DatabaseManager::class, 'pool'))->getValue();
        ConfigRepository::replace(['auth' => ['rate_limit' => ['connection' => 'rate-test', 'login_attempts' => 1,
            'mail_attempts' => 1, 'ip_attempts' => 2, 'window_seconds' => 10]],
            'mail' => ['rate_limit' => ['connection' => 'rate-test', 'max_attempts' => 2, 'window_seconds' => 60]]]);
    }

    protected function tearDown(): void
    {
        if ($this->schema !== null) $this->pdo->exec('DROP DATABASE `' . $this->schema . '`');
        (new \ReflectionProperty(\DatabaseManager::class, 'pool'))->setValue(null, $this->pool);
        ConfigRepository::replace($this->configuration);
    }

    public static function engines(): array { return [['sqlite'], ['mysql']]; }

    private function connect(string $engine, bool $migrate = true): void
    {
        if ($engine === 'mysql') {
            if (!getenv('GFRAME_TEST_MYSQL_DSN')) self::markTestSkipped('Requiere GFRAME_TEST_MYSQL_DSN.');
            $this->pdo = new PDO(getenv('GFRAME_TEST_MYSQL_DSN'), getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '');
            $this->schema = 'gframe_rate_test_' . bin2hex(random_bytes(8));
            $this->pdo->exec('CREATE DATABASE `' . $this->schema . '`');
            $this->pdo->exec('USE `' . $this->schema . '`');
        } else $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new \ReflectionProperty(\DatabaseManager::class, 'pool'))->setValue(null, ['rate-test' => $this->pdo]);
        if ($migrate) {
            self::assertSame(2, MigrationRunner::frameworkDefault()->migrate($this->pdo, $engine, ['rate-limits'])['count']);
            self::assertSame(0, MigrationRunner::frameworkDefault()->migrate($this->pdo, $engine, ['rate-limits'])['count']);
        }
    }

    private function auth(): AuthRateLimiter { return new AuthRateLimiter(null, fn(): int => $this->now); }
    private function mail(): MailRateLimiter { return new MailRateLimiter(null, fn(): int => $this->now); }
    private function send(string $identity = 'ip'): array { return $this->mail()->run('contact', $identity, static fn(): array => ['status' => 'success']); }

    #[DataProvider('engines')]
    public function testAuthAtomicQuotasNormalizationExpiryAndNoIdentityDisclosure(string $engine): void
    {
        $this->connect($engine);
        self::assertSame('success', $this->auth()->reserve('login', 'USER@example.test', 'ip')['status']);
        self::assertSame(10, $this->auth()->reserve('login', 'user@example.test', 'other-ip')['data']['retry_after']);
        // The refused identity did not consume other-ip's quota.
        self::assertSame('success', $this->auth()->reserve('recovery', 'user@example.test', 'other-ip')['status']);
        self::assertSame('auth_rate_limited', $this->auth()->reserve('resend', 'user@example.test', 'ip')['code']);
        self::assertSame('success', $this->auth()->reserve('login', 'second@example.test', 'ip')['status']);
        self::assertSame('auth_rate_limited', $this->auth()->reserve('login', 'third@example.test', 'ip')['code']);
        self::assertStringNotContainsString('example.test', json_encode($this->pdo->query('SELECT * FROM gframe_rate_buckets')->fetchAll()));
        $this->now += 10;
        self::assertSame('success', $this->auth()->reserve('login', 'third@example.test', 'ip')['status']);
        self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM gframe_rate_buckets')->fetchColumn());
    }

    #[DataProvider('engines')]
    public function testMailSlidingWindowLowerQuotaRefundAndCleanup(string $engine): void
    {
        $this->connect($engine);
        self::assertSame('success', $this->send()['status']);
        $this->now += 10;
        self::assertSame('success', $this->send()['status']);
        self::assertSame(50, $this->send()['data']['retry_after']);
        ConfigRepository::merge(['mail' => ['rate_limit' => ['max_attempts' => 1]]]);
        self::assertSame(60, $this->send()['data']['retry_after']);
        $this->now += 50;
        self::assertSame(10, $this->send()['data']['retry_after']);
        $this->now += 10;
        $failed = $this->mail()->run('contact', 'ip', static fn(): array => ['status' => 'error', 'code' => 'mail_send_failed']);
        self::assertSame('mail_send_failed', $failed['code']);
        try {
            $this->mail()->run('contact', 'ip', static function (): array { throw new \RuntimeException('transport'); });
            self::fail('Expected transport error');
        } catch (\RuntimeException $exception) { self::assertSame('transport', $exception->getMessage()); }
        self::assertSame('success', $this->send()['status']);
        self::assertSame('success', $this->send('other')['status']);
        self::assertSame(2, (new DatabaseRateLimitStore($this->pdo))->prune($this->now + 60));
        self::assertSame(0, (new DatabaseRateLimitStore($this->pdo))->prune($this->now + 60));
    }

    #[DataProvider('engines')]
    public function testMissingSchemaAndCallerTransactionsFailClosed(string $engine): void
    {
        $this->connect($engine, false);
        $never = static function (): array { self::fail('Transport must not run'); };
        self::assertSame('auth_rate_limit_unavailable', $this->auth()->reserve('login', 'email', 'ip')['code']);
        self::assertSame('mail_rate_limit_unavailable', $this->mail()->run('contact', 'ip', $never)['code']);
        MigrationRunner::frameworkDefault()->migrate($this->pdo, $engine, ['rate-limits']);
        $this->pdo->beginTransaction();
        self::assertSame('auth_rate_limit_unavailable', $this->auth()->reserve('login', 'email', 'ip')['code']);
        self::assertSame('mail_rate_limit_unavailable', $this->mail()->run('contact', 'ip', $never)['code']);
        self::assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
    }

    #[DataProvider('engines')]
    public function testFreshInstallAndBaseline(string $engine): void
    {
        $this->connect($engine, false);
        SchemaInstaller::frameworkDefault()->install($this->pdo, $engine, false, ['rate-limits']);
        MigrationRunner::frameworkDefault()->baseline($this->pdo, $engine, ['rate-limits']);
        self::assertSame(0, MigrationRunner::frameworkDefault()->migrate($this->pdo, $engine, ['rate-limits'])['count']);
        self::assertSame('success', $this->send()['status']);
    }

    #[DataProvider('engines')]
    public function testMailServiceRefundsValidationAndLaunchFailuresWithoutHoldingATransaction(string $engine): void
    {
        $this->connect($engine);
        $pdo = $this->pdo;
        $async = new class($pdo) extends \Async {
            public function __construct(private PDO $pdo) {}
            public function create(\Closure $closure): void
            {
                TestCase::assertFalse($this->pdo->inTransaction());
                throw new \RuntimeException('launch failed');
            }
        };
        $mail = new MailService(null, null, $async, $this->mail());
        $options = ['rate_limit' => ['scope' => 'contact', 'identity' => 'ip']];
        self::assertSame('invalid_email', $mail->sendHtml('invalid', 'Subject', 'Body', $options)['code']);
        self::assertSame('mail_queue_failed', $mail->sendHtmlAsync('user@example.test', 'Subject', 'Body', $options)['code']);
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM gframe_rate_reservations')->fetchColumn());
        self::assertSame('success', $this->send()['status']);
        self::assertSame('success', $this->send()['status']);
        self::assertSame('mail_rate_limited', $this->send()['code']);
    }

    public function testConcurrentMysqlProcessesReserveOnlyOneSlotAndRefundOnlyTheirOwnToken(): void
    {
        $this->connect('mysql');
        foreach (['auth', 'mail'] as $kind) {
            $processes = [];
            try {
                for ($i = 0; $i < 8; $i++) {
                    $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/database-rate-worker.php'],
                        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    self::assertIsResource($process);
                    fwrite($pipes[0], json_encode(['schema' => $this->schema, 'kind' => $kind]));
                    fclose($pipes[0]);
                    $processes[] = [$process, $pipes];
                }
                $accepted = 0;
                foreach ($processes as [$process, $pipes]) {
                    $result = json_decode(stream_get_contents($pipes[1]), true);
                    self::assertIsArray($result, stream_get_contents($pipes[2]));
                    if ($result['status'] === 'success') $accepted++;
                    else self::assertSame($kind . '_rate_limited', $result['code']);
                    fclose($pipes[1]); fclose($pipes[2]);
                    self::assertSame(0, proc_close($process));
                }
                $processes = [];
                self::assertSame(1, $accepted);
            } finally {
                foreach ($processes as [$process, $pipes]) {
                    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
                }
            }
        }
        $store = new DatabaseRateLimitStore($this->pdo);
        $first = $store->reserveMail('refund', 2, 60, 1000);
        $second = $store->reserveMail('refund', 2, 60, 1000);
        $store->releaseMail($first['token']);
        $store->releaseMail($first['token']);
        self::assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM gframe_rate_reservations WHERE bucket_key = 'refund'")->fetchColumn());
        self::assertNotNull($second['token']);
    }

    public function testMysqlContentionFailsClosedAndRestoresConnectionTimeout(): void
    {
        $this->connect('mysql');
        $other = new PDO(getenv('GFRAME_TEST_MYSQL_DSN'), getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '');
        $other->exec('USE `' . $this->schema . '`');
        $before = $this->pdo->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
        $other->beginTransaction();
        try {
            $other->exec('UPDATE gframe_rate_locks SET revision = revision + 1');
            $started = microtime(true);
            self::assertSame('auth_rate_limit_unavailable', $this->auth()->reserve('login', 'email', 'ip')['code']);
            self::assertLessThan(4, microtime(true) - $started);
            self::assertSame('mail_rate_limit_unavailable', $this->mail()->run('contact', 'ip', static function (): array {
                self::fail('A blocked store must not call the transport.');
            })['code']);
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame($before, $this->pdo->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn());
        } finally { $other->rollBack(); }
        self::assertSame('success', $this->send()['status']);
    }
}
