<?php

namespace GFrame\Notifications;

use GFrame\Notifications\Contracts\LeasedNotificationQueueRepository;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use InvalidArgumentException;

final class NotificationQueueLease
{
    public static function renew(NotificationQueueRepository $queue, array $job): bool
    {
        return !$queue instanceof LeasedNotificationQueueRepository || $queue->renewReservation($job);
    }

    public static function finish(NotificationQueueRepository $queue, array $job, string $status, ?string $error = null, ?string $availableAt = null): bool
    {
        if ($queue instanceof LeasedNotificationQueueRepository) return $queue->finishReservation($job, $status, $error, $availableAt);
        $id = (int)($job['notification_id'] ?? 0);
        switch ($status) {
            case 'sent': $queue->markSent($id); break;
            case 'failed': $queue->markFailed($id, $error ?? ''); break;
            case 'pending': $queue->releaseForRetry($id, $error ?? '', $availableAt ?? date('Y-m-d H:i:s')); break;
            default: throw new InvalidArgumentException('Estado de finalización inválido.');
        }
        return true;
    }
}
