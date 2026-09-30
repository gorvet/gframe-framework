<?php

namespace GFrame\Notifications\Email;

use GFrame\Config\ConfigRepository;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use GFrame\Notifications\NotificationBatchProcessor;
use GFrame\Notifications\NotificationQueueWorker;
use GFrame\Notifications\NotificationQueueModel;
use Exception;

final class EmailQueueProcessor implements NotificationBatchProcessor
{
    public function __construct(
        private readonly NotificationQueueRepository $queue = new NotificationQueueModel(),
        private readonly EmailNotificationTransport $transport = new EmailNotificationTransport()
    ) {
    }

    public function processNotificationBatch(int $batch): array
    {
        $sent = 0; $retried = 0; $failed = 0;
        $maxAttempts = max(1, (int)ConfigRepository::get('notifications.email.max_attempts', 5));
        $delay = max(1, (int)ConfigRepository::get('notifications.email.retry_delay_seconds', 300));
        foreach ($this->queue->reserve(NotificationQueueWorker::normalizeBatch($batch), 'email') as $notification) {
            $id = (int)($notification['notification_id'] ?? 0);
            try {
                $this->transport->send($notification);
                $this->queue->markSent($id);
                $sent++;
            } catch (Exception $exception) {
                $attempts = (int)($notification['attempts'] ?? 0);
                if ($attempts >= $maxAttempts) {
                    $this->queue->markFailed($id, $exception->getMessage());
                    $failed++;
                } else {
                    $this->queue->releaseForRetry($id, $exception->getMessage(), date('Y-m-d H:i:s', time() + ($delay * $attempts)));
                    $retried++;
                }
            }
        }
        return ['status' => 'success', 'code' => 'email_batch_processed', 'data' => ['processed' => $sent + $retried + $failed, 'sent' => $sent, 'retried' => $retried, 'failed' => $failed]];
    }
}
