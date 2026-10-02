<?php

namespace GFrame\Tests;

use GFrame\Notifications\InboxQueueProcessor;
use GFrame\Notifications\NotificationQueueModel;
use GFrame\Notifications\NotificationModel;
use GFrame\Notifications\NotificationService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class InboxQueueProcessorTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testQueuedCampaignAppearsInInboxWithoutConsumingTheEmailChannel(): void
    {
        define('DB_DEFAULT_CONNECTION', 'inbox_queue_test');
        define('DB_CONNECTIONS', ['inbox_queue_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/modules/notifications/database/sqlite.sql'));
        $queue = new NotificationQueueModel();
        $queue->enqueue(['channel' => 'inbox', 'recipient' => '5', 'payload' => ['campaign_id' => 7, 'title' => 'Campaña', 'message' => 'Mensaje', 'importance' => 'warning', 'action_url' => '/account']]);
        $queue->enqueue(['channel' => 'email', 'recipient' => 'ada@example.test', 'payload' => ['title' => 'Correo', 'message' => 'Mensaje']]);
        $worker = new InboxQueueProcessor();
        self::assertSame(1, $worker->processNotificationBatch(120)['data']['sent']);
        $inbox = (new NotificationService(new NotificationModel()))->inbox(5);
        self::assertSame(1, $inbox['data']['unread']);
        self::assertSame('Campaña', $inbox['data']['items'][0]['title']);
        self::assertSame('warning', $inbox['data']['items'][0]['importance']);
        self::assertSame('/account', $inbox['data']['items'][0]['action_url']);
        self::assertSame('pending', $pdo->query("SELECT status FROM notification_queue WHERE channel = 'email'")->fetchColumn());
        self::assertSame(0, $worker->processNotificationBatch(120)['data']['sent']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM user_notifications')->fetchColumn());
    }

    #[RunInSeparateProcess]
    public function testDeliveryAndQueueStatusRollbackTogetherWhenMarkingSentFails(): void
    {
        define('DB_DEFAULT_CONNECTION', 'inbox_queue_rollback_test');
        define('DB_CONNECTIONS', ['inbox_queue_rollback_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/modules/notifications/database/sqlite.sql'));
        $queue = new NotificationQueueModel();
        $id = $queue->enqueue(['channel' => 'inbox', 'recipient' => '5', 'payload' => ['title' => 'Aviso', 'message' => 'Mensaje']]);
        $pdo->exec("CREATE TRIGGER fail_mark_sent BEFORE UPDATE ON notification_queue WHEN NEW.status = 'sent' BEGIN SELECT RAISE(ABORT, 'test-only mark failure'); END");
        $previousLog = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try { self::assertSame('inbox_batch_failed', (new InboxQueueProcessor())->processNotificationBatch(120)['code']); }
        finally { ini_set('error_log', (string)$previousLog); }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM user_notifications')->fetchColumn());
        self::assertSame('failed', $pdo->query('SELECT status FROM notification_queue')->fetchColumn());
        $pdo->exec('DROP TRIGGER fail_mark_sent');
        $queue->retry($id);
        self::assertSame(1, (new InboxQueueProcessor())->processNotificationBatch(120)['data']['sent']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM user_notifications')->fetchColumn());
    }
}
