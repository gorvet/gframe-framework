<?php

namespace GFrame\Notifications;

use GFrame\Notifications\Contracts\NotificationTransport;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use Exception;

final class NotificationQueueService implements NotificationBatchProcessor
{
    public function __construct(
        private readonly NotificationQueueRepository $notifications,
        private readonly NotificationTransport $transport
    ) {
    }

    public function enqueue(string $channel, string $recipient, array $payload, ?int $tenantID = null): array
    {
        $channel = trim($channel);
        $recipient = trim($recipient);
        if ($channel === '' || $recipient === '') {
            return ['status' => 'error', 'code' => 'invalid_notification'];
        }

        try {
            $id = $this->notifications->enqueue([
                'tenant_id' => $tenantID, 'channel' => $channel,
                'recipient' => $recipient, 'payload' => $payload,
            ]);
            return ['status' => 'success', 'code' => 'notification_queued', 'data' => ['notification_id' => $id]];
        } catch (Exception $exception) {
            error_log('[GFrame Notification Queue] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'notification_queue_failed'];
        }
    }

    public function processNotificationBatch(int $batch): array
    {
        $sent = 0;
        $failed = 0;
        $lost = 0;

        foreach ($this->notifications->reserve(NotificationQueueWorker::normalizeBatch($batch)) as $notification) {
            try {
                if (!NotificationQueueLease::renew($this->notifications, $notification)) { $lost++; continue; }
                $this->transport->send($notification);
                if (NotificationQueueLease::finish($this->notifications, $notification, 'sent')) $sent++;
                else $lost++;
            } catch (Exception $exception) {
                if (NotificationQueueLease::finish($this->notifications, $notification, 'failed', $exception->getMessage())) $failed++;
                else $lost++;
            }
        }

        return ['status' => 'success', 'code' => 'notification_batch_processed', 'data' => [
            'processed' => $sent + $failed, 'sent' => $sent, 'failed' => $failed, 'lost' => $lost,
        ]];
    }
}
