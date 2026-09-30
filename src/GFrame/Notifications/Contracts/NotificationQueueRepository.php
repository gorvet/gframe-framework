<?php

namespace GFrame\Notifications\Contracts;

interface NotificationQueueRepository
{
    public function enqueue(array $notification): int;
    public function reserve(int $limit, ?string $channel = null): array;
    public function markSent(int $notificationID): void;
    public function markFailed(int $notificationID, string $error): void;
    public function releaseForRetry(int $notificationID, string $error, string $availableAt): void;
}
