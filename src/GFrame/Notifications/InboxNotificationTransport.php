<?php

namespace GFrame\Notifications;

use GFrame\Notifications\Contracts\NotificationTransport;
use RuntimeException;

final class InboxNotificationTransport implements NotificationTransport
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function send(array $notification): void
    {
        $result = $this->notifications->notify(
            (int)($notification['recipient'] ?? $notification['user_id'] ?? 0),
            (array)($notification['payload'] ?? $notification),
            isset($notification['tenant_id']) ? (int)$notification['tenant_id'] : null
        );
        if (($result['status'] ?? 'error') !== 'success') throw new RuntimeException((string)($result['code'] ?? 'notification_create_failed'));
    }
}
