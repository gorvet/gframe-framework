<?php

namespace GFrame\Tests;

use GFrame\Notifications\NotificationQueueModel;
use GFrame\Notifications\NotificationQueueService;
use GFrame\Notifications\Contracts\NotificationTransport;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NotificationQueueLeaseTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRecoveryFencesOldWorkersAndRespectsChannelsAndRetryTime(): void
    {
        $pdo = $this->database();
        $first = new NotificationQueueModel();
        $second = new NotificationQueueModel();
        $id = $first->enqueue(['channel' => 'email', 'recipient' => 'test@example.test']);
        $other = $first->enqueue(['channel' => 'inbox', 'recipient' => '1']);
        $job = $first->reserve(1, 'email')[0];
        self::assertSame(1, $job['attempts']);
        self::assertSame([], $second->reserve(1, 'email'));
        self::assertTrue($first->renewReservation($job));
        self::assertTrue($first->renewReservation($job));
        $pdo->exec("UPDATE notification_queue SET available_at = '2000-01-01 00:00:00' WHERE notification_id = $id");
        self::assertFalse($first->renewReservation($job));
        self::assertFalse($first->finishReservation($job, 'sent'));
        self::assertSame(0, $second->recoverExpiredReservations('inbox'));
        $replacement = $second->reserve(1, 'email')[0];
        self::assertSame(2, $replacement['attempts']);
        self::assertFalse($first->finishReservation($job, 'sent'));
        self::assertFalse($first->finishReservation($job, 'failed', 'old worker'));
        self::assertFalse($first->finishReservation($job, 'pending', 'old worker'));
        self::assertTrue($second->finishReservation($replacement, 'pending', 'retry', '2099-01-01 00:00:00'));
        self::assertSame([], $first->reserve(1, 'email'));
        self::assertSame('pending', $pdo->query("SELECT status FROM notification_queue WHERE notification_id = $other")->fetchColumn());
        $second->retry($id);
        $current = $second->reserve(1, 'email')[0];
        self::assertSame(3, $current['attempts']);
        self::assertTrue($second->finishReservation($current, 'sent'));
        self::assertFalse($second->finishReservation($current, 'failed', 'duplicate acknowledgement'));
        self::assertSame('sent', $pdo->query("SELECT status FROM notification_queue WHERE notification_id = $id")->fetchColumn());
        self::assertSame([], $first->reserve(1, 'email'));
    }

    #[RunInSeparateProcess]
    public function testBatchDoesNotConfirmAnExpiredExternalSend(): void
    {
        $pdo = $this->database();
        $queue = new NotificationQueueModel();
        $id = $queue->enqueue(['channel' => 'email', 'recipient' => 'test@example.test']);
        $transport = new class($pdo) implements NotificationTransport {
            public function __construct(private \PDO $pdo) {}
            public function send(array $notification): void {
                $this->pdo->exec("UPDATE notification_queue SET available_at = '2000-01-01 00:00:00' WHERE notification_id = " . (int)$notification['notification_id']);
            }
        };
        $result = (new NotificationQueueService($queue, $transport))->processNotificationBatch(20);
        self::assertSame(0, $result['data']['sent']);
        self::assertSame(1, $result['data']['lost']);
        self::assertSame('processing', $pdo->query("SELECT status FROM notification_queue WHERE notification_id = $id")->fetchColumn());
        self::assertSame(1, $queue->recoverExpiredReservations());
        $job = $queue->reserve(1)[0];
        \ORM::beginTransaction();
        self::assertTrue($queue->finishReservation($job, 'sent'));
        \ORM::rollBack();
        self::assertSame('processing', $pdo->query("SELECT status FROM notification_queue WHERE notification_id = $id")->fetchColumn());
        self::assertTrue($queue->finishReservation($job, 'failed', 'rolled back delivery'));
    }

    private function database(): \PDO
    {
        define('DB_DEFAULT_CONNECTION', 'queue_lease_test');
        define('DB_CONNECTIONS', ['queue_lease_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/notifications/database/sqlite.sql'));
        return $pdo;
    }
}
