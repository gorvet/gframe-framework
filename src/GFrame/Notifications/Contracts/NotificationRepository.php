<?php

namespace GFrame\Notifications\Contracts;

interface NotificationRepository
{
    public function createInboxNotification(array $notification): int;
    public function inbox(int $userID, ?int $tenantID, int $limit): array;
    public function unreadCount(int $userID, ?int $tenantID): int;
    public function history(int $userID, ?int $tenantID, int $page, int $perPage, bool $unreadOnly): array;
    public function markRead(int $notificationID, int $userID, ?int $tenantID): bool;
    public function markAllRead(int $userID, ?int $tenantID): int;
    public function deleteForUser(int $notificationID, int $userID, ?int $tenantID): bool;
    public function cleanupExpired(string $now): int;
}
