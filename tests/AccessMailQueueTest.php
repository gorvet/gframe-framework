<?php

namespace GFrame\Tests;

use GFrame\Mail\AccessMailService;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use GFrame\Notifications\Email\EmailNotificationTransport;
use GFrame\Notifications\Email\EmailQueueProcessor;
use GFrame\Notifications\NotificationQueueModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AccessMailQueueTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testNativeQueueDeliversCurrentTokenAndPermanentlyRejectsSupersededOrExpiredLinks(): void
    {
        require_once __DIR__ . '/AuthModelTest.php';
        require_once __DIR__ . '/NotificationEmailTest.php';
        define('DB_DEFAULT_CONNECTION', 'access_queue_test');
        define('DB_CONNECTIONS', ['access_queue_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection('access_queue_test');
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/notifications/database/sqlite.sql'));
        \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['notifications-email'], __DIR__);
        $auth = new InMemoryAuthModel();
        $registration = $auth->registerAcount('user@example.test', 'Password-123');
        $token = $registration['data']['token'];
        $sender = new MemoryMailSender();
        $service = new AccessMailService($sender);
        $queue = new NotificationQueueModel();
        $worker = new EmailQueueProcessor($queue, new EmailNotificationTransport($sender, $auth));
        self::assertSame('notification_queued', $service->send('user@example.test', 'Verifica', ['aHref' => 'https://example.test/login/verify?v=' . $token], 'User', $token)['code']);
        self::assertSame([], $sender->last);
        self::assertSame(1, $worker->processNotificationBatch(20)['data']['sent']);
        self::assertSame('mailTemplate', $sender->last['template']);
        self::assertSame('User', $sender->last['options']['recipient_name']);

        $sender->last = [];
        $service->send('user@example.test', 'Viejo', [], 'User', $token);
        $auth->recoveryAcount('user@example.test');
        $result = $worker->processNotificationBatch(20);
        self::assertSame(1, $result['data']['failed']);
        self::assertSame(0, $result['data']['retried']);
        self::assertSame([], $sender->last);
        self::assertSame('access_mail_expired', $pdo->query("SELECT last_error FROM notification_queue WHERE status='failed'")->fetchColumn());

        $current = $auth->findByEmail('user@example.test')['token'];
        $service->send('user@example.test', 'Vencido', [], 'User', $current);
        $row = $pdo->query("SELECT notification_id,payload_json FROM notification_queue WHERE status='pending'")->fetch(\PDO::FETCH_ASSOC);
        $payload = json_decode($row['payload_json'], true);
        $payload['expires_at'] = time() - 1;
        $pdo->prepare('UPDATE notification_queue SET payload_json=? WHERE notification_id=?')->execute([json_encode($payload), $row['notification_id']]);
        self::assertSame(1, $worker->processNotificationBatch(20)['data']['failed']);
        self::assertSame([], $sender->last);
    }

    public function testQueueFailureDoesNotFallBackToAnotherMailSend(): void
    {
        require_once __DIR__ . '/NotificationEmailTest.php';
        $sender = new MemoryMailSender();
        $result = (new AccessMailService($sender, new UnavailableAccessQueue()))->send('user@example.test', 'Test', [], 'User', 'token');
        self::assertSame('notification_queue_failed', $result['code']);
        self::assertSame([], $sender->last);
    }
}

final class UnavailableAccessQueue implements NotificationQueueRepository
{
    public function enqueue(array $notification): int { throw new \RuntimeException('offline'); }
    public function reserve(int $limit, ?string $channel = null): array { return []; }
    public function markSent(int $notificationID): void {}
    public function markFailed(int $notificationID, string $error): void {}
    public function releaseForRetry(int $notificationID, string $error, string $availableAt): void {}
}
