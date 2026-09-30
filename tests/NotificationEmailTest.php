<?php

namespace GFrame\Tests;

use GFrame\Mail\Contracts\MailSender;
use GFrame\Mail\MailService;
use GFrame\Mail\MailTemplateRegistry;
use GFrame\Mail\SmtpConfiguration;
use GFrame\Notifications\Email\EmailNotificationTransport;
use GFrame\Notifications\Email\EmailQueueProcessor;
use GFrame\Notifications\Email\EmailQueueCronHandler;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use GFrame\Notifications\NotificationQueueModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NotificationEmailTest extends TestCase
{
    private array $environment = [];

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === null) unset($_ENV[$key], $_SERVER[$key]); else $_ENV[$key] = $value;
            putenv($value === null ? $key : $key . '=' . $value);
        }
    }

    public function testSmtpConfigurationComesOnlyFromEnvironment(): void
    {
        $this->setEnvironment('MAIL_HOST', 'smtp.env.test');
        $this->setEnvironment('MAIL_PORT', '2525');
        $this->setEnvironment('MAIL_FROM_ADDRESS', 'sender@example.test');
        $configuration = (new SmtpConfiguration())->resolve();

        self::assertSame('smtp.env.test', $configuration['host']);
        self::assertSame(2525, $configuration['port']);
        self::assertSame('sender@example.test', $configuration['from']);
    }

    public function testMailServiceReturnsStableErrorWhenEnvironmentIsMissing(): void
    {
        $this->setEnvironment('MAIL_HOST', '');
        $result = (new MailService())->sendHtml('user@example.test', 'Asunto', '<p>Mensaje</p>');
        self::assertSame(['status' => 'error', 'code' => 'mail_host_not_configured'], $result);
    }

    public function testTemplatesAreDiscoveredRenderedAndThemedFromOneDirectory(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-mail-' . bin2hex(random_bytes(5));
        mkdir($directory, 0775, true);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'welcome.html', '<h1>{{title}}</h1><p>{{mailSiteName}}</p>');
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'welcome.json', '{"name":"Bienvenida","variables":["title","mailSiteName"]}');
        $registry = new MailTemplateRegistry($directory);

        self::assertSame('welcome', $registry->all()[0]['id']);
        self::assertStringContainsString('Hola &lt;script&gt;', $registry->render('welcome', ['title' => 'Hola <script>']));
        self::assertStringNotContainsString('{{mailSiteName}}', $registry->render('welcome', ['title' => 'Hola']));
        unlink($directory . DIRECTORY_SEPARATOR . 'welcome.json');
        unlink($directory . DIRECTORY_SEPARATOR . 'welcome.html');
        rmdir($directory);
    }

    public function testNotificationEmailIsOnlyAnAdapterToCoreMail(): void
    {
        $sender = new MemoryMailSender();
        (new EmailNotificationTransport($sender))->send([
            'recipient' => 'user@example.test',
            'payload' => ['subject' => 'Aviso', 'template' => 'notification', 'variables' => ['title' => 'Hola']],
        ]);

        self::assertSame('user@example.test', $sender->last['recipient']);
        self::assertSame('notification', $sender->last['template']);
    }

    public function testCoreMailQueuesSerializableClosuresThroughAsync(): void
    {
        $async = new MemoryAsync();
        $result = (new MailService(null, null, $async))->sendTemplateAsync(
            'user@example.test',
            'Aviso',
            'notification',
            ['title' => 'Hola']
        );

        self::assertSame(['status' => 'success', 'code' => 'mail_queued'], $result);
        self::assertInstanceOf(\Closure::class, $async->job);
        self::assertNotSame('', \ClosureWrapper::serialize($async->job));
    }

    public function testAddonHasNoDatabaseOrAdministrativeSmtpConfiguration(): void
    {
        $module = require dirname(__DIR__) . '/resources/modules/notifications-email/module.php';
        self::assertSame(['notifications', 'cron-runner'], $module['dependencies']);
        self::assertArrayNotHasKey('schemas', $module);
        self::assertArrayNotHasKey('migrations', $module);
        self::assertSame([
            ['source' => 'application/mail', 'target' => 'app/views/templates/mail'],
            ['source' => 'application/cron', 'target' => 'config/cron'],
        ], $module['application']);
        self::assertSame(0, (new \ReflectionClass(EmailQueueProcessor::class))->getConstructor()->getNumberOfRequiredParameters());
    }

    public function testEmailRetriesUseReservedAttemptNumber(): void
    {
        $queue = new MemoryEmailQueueRepository();
        $sender = new MemoryMailSender();
        $sender->fail = true;
        $processor = new EmailQueueProcessor($queue, new EmailNotificationTransport($sender));
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = $processor->processNotificationBatch(20);
            self::assertSame(1, $result['data']['processed']);
            self::assertSame($attempt < 5 ? 'pending' : 'failed', $queue->status);
        }
        self::assertSame(5, $queue->attempts);
    }

    #[RunInSeparateProcess]
    public function testCronRegistrationCreatesRecurringEmailWorker(): void
    {
        define('DB_DEFAULT_CONNECTION', 'email_cron_test');
        define('DB_CONNECTIONS', ['email_cron_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection('email_cron_test');
        self::assertInstanceOf(\PDO::class, $pdo);
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/sqlite.sql'));
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/modules/notifications/database/sqlite.sql'));
        include dirname(__DIR__) . '/resources/modules/notifications-email/application/cron/register-email-queue.php';
        $task = (new \CronDataProvider())->findByKey('notifications.email.queue');
        self::assertSame(EmailQueueCronHandler::class, $task['handler_class']);
        self::assertSame(60, (int)$task['repeat_interval_seconds']);
        $result = (new \CronScheduler())->runDue();
        self::assertSame('success', $result['status']);
        self::assertSame(1, $result['data']['processed']);
        self::assertSame('pending', (new \CronDataProvider())->findByKey('notifications.email.queue')['status']);
        $queue = new NotificationQueueModel();
        $queue->enqueue(['channel' => 'email', 'recipient' => 'user@example.test', 'payload' => ['subject' => 'Aviso']]);
        $reserved = $queue->reserve(20, 'email');
        self::assertCount(1, $reserved);
        self::assertSame(1, (int)$reserved[0]['attempts']);
    }

    private function setEnvironment(string $key, string $value): void
    {
        $this->environment[$key] = $_ENV[$key] ?? null;
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

final class MemoryMailSender implements MailSender
{
    public array $last = [];
    public bool $fail = false;
    public function sendTemplate(string $recipient, string $subject, string $template, array $variables = [], array $options = []): array
    {
        $this->last = compact('recipient', 'subject', 'template', 'variables', 'options');
        return $this->fail ? ['status' => 'error', 'code' => 'mail_send_failed'] : ['status' => 'success', 'code' => 'mail_sent'];
    }
    public function sendHtml(string $recipient, string $subject, string $html, array $options = []): array
    {
        $this->last = compact('recipient', 'subject', 'html', 'options');
        return ['status' => 'success', 'code' => 'mail_sent'];
    }
}

final class MemoryEmailQueueRepository implements NotificationQueueRepository
{
    public int $attempts = 0;
    public string $status = 'pending';
    public function enqueue(array $notification): int { return 1; }
    public function reserve(int $limit, ?string $channel = null): array
    {
        if ($this->status !== 'pending' || $channel !== 'email') return [];
        $this->attempts++;
        $this->status = 'processing';
        return [['notification_id' => 1, 'channel' => 'email', 'recipient' => 'user@example.test', 'payload' => ['subject' => 'Aviso'], 'attempts' => $this->attempts]];
    }
    public function markSent(int $notificationID): void { $this->status = 'sent'; }
    public function markFailed(int $notificationID, string $error): void { $this->status = 'failed'; }
    public function releaseForRetry(int $notificationID, string $error, string $availableAt): void { $this->status = 'pending'; }
}

final class MemoryAsync extends \Async
{
    public ?\Closure $job = null;
    public function create(\Closure $closure): void { $this->job = $closure; }
}
