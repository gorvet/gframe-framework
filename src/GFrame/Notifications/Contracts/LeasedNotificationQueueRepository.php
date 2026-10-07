<?php

namespace GFrame\Notifications\Contracts;

/** Optional extension: legacy repositories keep their original contract. */
interface LeasedNotificationQueueRepository extends NotificationQueueRepository
{
    public function renewReservation(array $notification): bool;
    public function finishReservation(array $notification, string $status, ?string $error = null, ?string $availableAt = null): bool;
}
