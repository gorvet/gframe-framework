<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Mail\MailRateLimiter;
use GFrame\Mail\MailService;
use PHPUnit\Framework\TestCase;

final class MailRateLimiterTest extends TestCase
{
    private string $directory;
    private int $now = 10000;
    private array $configuration;
    private array $environment = [];

    protected function setUp(): void
    {
        $this->configuration = ConfigRepository::get();
        ConfigRepository::merge(['mail' => ['rate_limit' => ['enabled' => true, 'max_attempts' => 2, 'window_seconds' => 60]]]);
        foreach (['MAIL_RATE_LIMIT_ENABLED', 'MAIL_RATE_LIMIT_MAX_ATTEMPTS', 'MAIL_RATE_LIMIT_WINDOW_SECONDS', 'MAIL_HOST'] as $key) {
            $this->environment[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        $this->directory = sys_get_temp_dir() . '/gframe-mail-rate-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        ConfigRepository::replace($this->configuration);
        foreach ($this->environment as $key => [$env, $server, $process]) {
            if ($env === null) unset($_ENV[$key]); else $_ENV[$key] = $env;
            if ($server === null) unset($_SERVER[$key]); else $_SERVER[$key] = $server;
            putenv($process === false ? $key : $key . '=' . $process);
        }
        foreach (glob($this->directory . '/*') ?: [] as $path) unlink($path);
        if (is_dir($this->directory)) rmdir($this->directory);
    }

    public function testWindowIsSharedBetweenInstancesAndReturnsActualRetryTime(): void
    {
        $this->send();
        $this->now += 10;
        $this->send();
        $this->now += 10;
        $called = false;
        $blocked = $this->limiter()->run('contact', '192.0.2.1', static function () use (&$called): array {
            $called = true;
            return ['status' => 'success'];
        });
        self::assertFalse($called);
        self::assertSame('mail_rate_limited', $blocked['code']);
        self::assertSame(40, $blocked['data']['retry_after']);
        $this->now += 40;
        self::assertSame('success', $this->send()['status']);
        self::assertSame(10, $this->send()['data']['retry_after']);
    }

    public function testDifferentIdentitiesAndScopesHaveIndependentQuotas(): void
    {
        $this->send(); $this->send();
        self::assertSame('success', $this->send('contact', '192.0.2.2')['status']);
        self::assertSame('success', $this->send('password-reset')['status']);
        foreach (glob($this->directory . '/*') as $file) {
            self::assertStringNotContainsString('192.0.2.', $file . file_get_contents($file));
        }
    }

    public function testLoweringQuotaReportsWhenEnoughSlotsWillExpire(): void
    {
        $this->send();
        $this->now += 10;
        $this->send();
        $_ENV['MAIL_RATE_LIMIT_MAX_ATTEMPTS'] = '1';
        self::assertSame(60, $this->send()['data']['retry_after']);
        $this->now += 50;
        self::assertSame(10, $this->send()['data']['retry_after']);
        $this->now += 10;
        self::assertSame('success', $this->send()['status']);
    }

    public function testFailuresAndExceptionsDoNotConsumeQuota(): void
    {
        $limiter = $this->limiter();
        $limiter->run('contact', '192.0.2.1', static fn(): array => ['status' => 'error', 'code' => 'mail_queue_failed']);
        try {
            $limiter->run('contact', '192.0.2.1', static function (): array { throw new \RuntimeException('Failed'); });
            self::fail('Expected exception');
        } catch (\RuntimeException $exception) {
            self::assertSame('Failed', $exception->getMessage());
        }
        self::assertSame('success', $this->send()['status']);
        self::assertSame('success', $this->send()['status']);
        self::assertSame('mail_rate_limited', $this->send()['code']);
    }

    public function testEnvironmentOverridesAndDisablingDoNotRequireStorage(): void
    {
        $_ENV['MAIL_RATE_LIMIT_MAX_ATTEMPTS'] = '1';
        $_ENV['MAIL_RATE_LIMIT_WINDOW_SECONDS'] = '120';
        $this->send();
        self::assertSame(120, $this->send()['data']['retry_after']);
        $_ENV['MAIL_RATE_LIMIT_ENABLED'] = 'false';
        self::assertSame('success', $this->send()['status']);
        self::assertSame('success', (new MailRateLimiter(''))->run('', '', static fn(): array => ['status' => 'success'])['status']);
    }

    public function testInvalidConfigurationAndUnavailableStoragePreventSending(): void
    {
        $never = static function (): array { self::fail('Sender must not run'); };
        self::assertSame('mail_rate_limit_invalid', $this->limiter()->run('contact', '', $never)['code']);
        $_ENV['MAIL_RATE_LIMIT_WINDOW_SECONDS'] = '0';
        self::assertSame('mail_rate_limit_invalid', $this->limiter()->run('contact', 'ip', $never)['code']);
        unset($_ENV['MAIL_RATE_LIMIT_WINDOW_SECONDS']);
        self::assertSame('mail_rate_limit_unavailable', (new MailRateLimiter(''))->run('contact', 'ip', $never)['code']);
        $this->send();
        file_put_contents(glob($this->directory . '/*')[0], '{broken');
        self::assertSame('mail_rate_limit_unavailable', $this->limiter()->run('contact', '192.0.2.1', $never)['code']);
    }

    public function testAsyncMethodsReserveOnceAndOrdinaryMailRemainsUnlimited(): void
    {
        $_ENV['MAIL_RATE_LIMIT_MAX_ATTEMPTS'] = '1';
        $_ENV['MAIL_HOST'] = '';
        $async = new class extends \Async {
            public int $calls = 0;
            public ?\Closure $job = null;
            public function create(\Closure $closure): void { $this->calls++; $this->job = $closure; }
        };
        $mail = new MailService(null, null, $async, $this->limiter());
        $options = ['rate_limit' => ['scope' => 'contact', 'identity' => '192.0.2.1']];
        self::assertSame('mail_queued', $mail->sendTemplateAsync('user@example.test', 'Subject', 'contactTemplate', [], $options)['code']);
        self::assertSame('mail_rate_limited', $mail->sendHtmlAsync('user@example.test', 'Subject', '<p>Test</p>', $options)['code']);
        self::assertSame(1, $async->calls);
        self::assertArrayNotHasKey('rate_limit', (new \ReflectionFunction($async->job))->getStaticVariables()['options']);
        $snapshot = file_get_contents(glob($this->directory . '/*')[0]);
        // El worker recibe opciones limpias y no consume un segundo cupo.
        (\ClosureWrapper::unserialize(\ClosureWrapper::serialize($async->job)))();
        self::assertSame($snapshot, file_get_contents(glob($this->directory . '/*')[0]));
        self::assertSame('mail_queued', $mail->sendHtmlAsync('user@example.test', 'Subject', '<p>Test</p>')['code']);
        self::assertSame(2, $async->calls);
    }

    public function testSyncMethodsReleaseQuotaOnValidationAndTemplateFailures(): void
    {
        $mail = new MailService(null, null, null, $this->limiter());
        $options = ['rate_limit' => ['scope' => 'contact', 'identity' => '192.0.2.1']];
        self::assertSame('invalid_email', $mail->sendHtml('invalid', 'Subject', 'Test', $options)['code']);
        self::assertSame('mail_template_failed', $mail->sendTemplate('user@example.test', 'Subject', 'missing-template', [], $options)['code']);
        self::assertSame('success', $this->send()['status']);
        self::assertSame('success', $this->send()['status']);
        self::assertSame('mail_rate_limited', $mail->sendHtml('user@example.test', 'Subject', 'Test', $options)['code']);
    }

    public function testConcurrentProcessesShareOneAvailableSlot(): void
    {
        mkdir($this->directory);
        $script = $this->directory . '/worker.php';
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';' . <<<'PHP'
$_ENV['MAIL_RATE_LIMIT_ENABLED'] = 'true';
$_ENV['MAIL_RATE_LIMIT_MAX_ATTEMPTS'] = '1';
$_ENV['MAIL_RATE_LIMIT_WINDOW_SECONDS'] = '60';
$result = (new \GFrame\Mail\MailRateLimiter($argv[1]))->run('contact', '192.0.2.1', static function (): array {
    usleep(200000);
    return ['status' => 'success'];
});
echo $result['code'] ?? $result['status'];
PHP);
        $processes = [];
        try {
            for ($index = 0; $index < 2; $index++) {
                $process = proc_open([PHP_BINARY, $script, $this->directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            $results = [];
            foreach ($processes as [$process, $pipes]) {
                $results[] = stream_get_contents($pipes[1]);
                self::assertSame('', stream_get_contents($pipes[2]));
                fclose($pipes[1]); fclose($pipes[2]);
                self::assertSame(0, proc_close($process));
            }
            $processes = [];
            sort($results);
            self::assertSame(['mail_rate_limited', 'success'], $results);
        } finally {
            foreach ($processes as [$process, $pipes]) {
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            }
        }
    }

    private function limiter(): MailRateLimiter
    {
        return new MailRateLimiter($this->directory, fn(): int => $this->now);
    }

    private function send(string $scope = 'contact', string $identity = '192.0.2.1'): array
    {
        return $this->limiter()->run($scope, $identity, static fn(): array => ['status' => 'success']);
    }
}
