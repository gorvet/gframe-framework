<?php

namespace GFrame\Notifications\Email;

use GFrame\Config\ConfigRepository;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use GFrame\Notifications\NotificationBatchProcessor;
use GFrame\Notifications\NotificationQueueWorker;
use GFrame\Notifications\NotificationQueueModel;
use GFrame\Notifications\NotificationQueueLease;
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
        $sent = 0; $retried = 0; $failed = 0; $lost = 0;
        $maxAttempts = max(1, (int)ConfigRepository::get('notifications.email.max_attempts', 5));
        $delay = max(1, (int)ConfigRepository::get('notifications.email.retry_delay_seconds', 300));
        foreach ($this->queue->reserve(NotificationQueueWorker::normalizeBatch($batch), 'email') as $notification) {
            try {
                if (!NotificationQueueLease::renew($this->queue, $notification)) { $lost++; continue; }
                $this->transport->send($notification);
                if (NotificationQueueLease::finish($this->queue, $notification, 'sent')) $sent++;
                else $lost++;
            } catch (Exception $exception) {
                $attempts = (int)($notification['attempts'] ?? 0);
                if ($attempts >= $maxAttempts) {
                    if (NotificationQueueLease::finish($this->queue, $notification, 'failed', $exception->getMessage())) $failed++;
                    else $lost++;
                } else {
                    if (NotificationQueueLease::finish($this->queue, $notification, 'pending', $exception->getMessage(), date('Y-m-d H:i:s', time() + ($delay * $attempts)))) $retried++;
                    else $lost++;
                }
            }
        }
        return ['status' => 'success', 'code' => 'email_batch_processed', 'data' => ['processed' => $sent + $retried + $failed, 'sent' => $sent, 'retried' => $retried, 'failed' => $failed, 'lost' => $lost]];
    }
}
