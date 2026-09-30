<?php

namespace GFrame\Notifications;

use Exception;
use GFrame\Notifications\Contracts\NotificationTransport;

final class NotificationTransportRegistry
{
    private array $transports = [];

    public function register(string $channel, NotificationTransport $transport): void
    {
        $channel = strtolower(trim($channel));
        if ($channel === '' || preg_match('/^[a-z0-9][a-z0-9_.-]{0,79}$/', $channel) !== 1) {
            throw new \InvalidArgumentException('El canal de notificaciones no es válido.');
        }
        $this->transports[$channel] = $transport;
    }

    public function has(string $channel): bool
    {
        return isset($this->transports[strtolower(trim($channel))]);
    }

    public function dispatch(string $channel, array $notification): array
    {
        $channel = strtolower(trim($channel));
        if (!$this->has($channel)) return ['status' => 'error', 'code' => 'notification_transport_not_registered'];
        try {
            $notification['channel'] = $channel;
            $this->transports[$channel]->send($notification);
            return ['status' => 'success', 'code' => 'notification_dispatched', 'data' => ['channel' => $channel]];
        } catch (Exception $exception) {
            error_log('[GFrame Notification Transport] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'notification_transport_failed', 'data' => ['channel' => $channel]];
        }
    }
}
