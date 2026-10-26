<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Mail\Contracts\SmtpConfigurationProvider;
use GFrame\Mail\MailService;
use PHPUnit\Framework\TestCase;

final class SmtpConfigurationProviderTest extends TestCase
{
    private array $previous;

    protected function setUp(): void { $this->previous = ConfigRepository::get(); }
    protected function tearDown(): void { ConfigRepository::replace($this->previous); }

    public function testRegisteredProviderIsReadAgainForEachSend(): void
    {
        ConfigRepository::merge(['mail' => ['smtp_provider' => MutableSmtpProvider::class]]);
        $mail = new MailService();
        MutableSmtpProvider::$settings = ['host' => ''];
        self::assertSame('mail_host_not_configured', $mail->sendHtml('a@example.test', 'Test', '')['code']);
        MutableSmtpProvider::$settings = ['host' => 'configured', 'from' => 'invalid'];
        self::assertSame('mail_sender_not_configured', $mail->sendHtml('a@example.test', 'Test', '')['code']);
    }

    public function testExplicitProviderTakesPrecedence(): void
    {
        MutableSmtpProvider::$settings = ['host' => ''];
        ConfigRepository::merge(['mail' => ['smtp_provider' => 'InvalidProvider']]);
        self::assertSame('mail_host_not_configured', (new MailService(new MutableSmtpProvider()))->sendHtml('a@example.test', 'Test', '')['code']);
    }

    public function testDatabaseProviderReadsUpdatedRowsWithoutReplacingService(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE smtp_settings (host TEXT, from_address TEXT)");
        $pdo->exec("INSERT INTO smtp_settings VALUES ('', 'invalid')");
        $provider = new class($pdo) implements SmtpConfigurationProvider {
            public function __construct(private \PDO $pdo) {}
            public function resolve(): array
            {
                $row = $this->pdo->query('SELECT host, from_address FROM smtp_settings')->fetch(\PDO::FETCH_ASSOC);
                return ['host' => $row['host'], 'from' => $row['from_address'], 'port' => 465,
                    'username' => '', 'password' => '', 'encryption' => 'ssl', 'from_name' => 'Test'];
            }
        };
        $mail = new MailService($provider);
        self::assertSame('mail_host_not_configured', $mail->sendHtml('a@example.test', 'Test', '')['code']);
        $pdo->exec("UPDATE smtp_settings SET host = 'configured'");
        self::assertSame('mail_sender_not_configured', $mail->sendHtml('a@example.test', 'Test', '')['code']);
    }

    public function testProviderFailureDoesNotExposeSecretsOrFallBack(): void
    {
        ConfigRepository::merge(['mail' => ['smtp_provider' => FailingSmtpProvider::class]]);
        $log = tempnam(sys_get_temp_dir(), 'smtp-log');
        $previous = ini_set('error_log', $log);
        try {
            self::assertSame(['status' => 'error', 'code' => 'mail_send_failed'], (new MailService())->sendHtml('a@example.test', 'Test', ''));
            self::assertStringNotContainsString('secret-password', file_get_contents($log));
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }
    }

    public function testIndependentAsyncWorkerBootsProviderFromProjectConfiguration(): void
    {
        $root = sys_get_temp_dir() . '/gframe-smtp-' . bin2hex(random_bytes(5));
        mkdir($root . '/config/routes', 0777, true);
        mkdir($root . '/app', 0777, true);
        file_put_contents($root . '/config/app.php', '<?php return ["mail" => ["smtp_provider" => "WorkerSmtpProvider"]];');
        file_put_contents($root . '/app/WorkerSmtpProvider.php', '<?php class WorkerSmtpProvider implements \\GFrame\\Mail\\Contracts\\SmtpConfigurationProvider { public function resolve(): array { file_put_contents(ABSPATH . "resolved", "yes"); return ["host" => ""]; } }');
        $async = new class extends \Async {
            public ?\Closure $job = null;
            public function create(\Closure $closure): void { $this->job = $closure; }
        };
        try {
            self::assertSame('mail_queued', (new MailService(async: $async))->sendHtmlAsync('a@example.test', 'Test', '')['code']);
            $serialized = \ClosureWrapper::serialize($async->job);
            self::assertStringNotContainsString('WorkerSmtpProvider', $serialized);
            $payload = base64_encode(json_encode(['root' => $root, 'closure' => base64_encode($serialized)]));
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/async-worker.php') . ' ' . escapeshellarg($payload), $output, $status);
            self::assertSame(0, $status);
            self::assertSame('yes', file_get_contents($root . '/resolved'));
        } finally {
            foreach (['resolved', 'config/app.php', 'app/WorkerSmtpProvider.php'] as $file) if (is_file($root . '/' . $file)) unlink($root . '/' . $file);
            rmdir($root . '/config/routes'); rmdir($root . '/config'); rmdir($root . '/app'); rmdir($root);
        }
    }
}

final class MutableSmtpProvider implements SmtpConfigurationProvider
{
    public static array $settings = ['host' => ''];
    public function resolve(): array { return self::$settings; }
}

final class FailingSmtpProvider implements SmtpConfigurationProvider
{
    public function resolve(): array { throw new \RuntimeException('secret-password'); }
}
