<?php

namespace GFrame\Notifications;

use Exception;
use GFrame\Notifications\Contracts\NotificationRepository;

class NotificationService
{
    public function __construct(protected readonly NotificationRepository $notifications)
    {
    }

    public function notify(int $userID, array $input, ?int $tenantID = null): array
    {
        if ($userID <= 0) return $this->error('invalid_user');
        $title = mb_substr(trim(strip_tags((string)($input['title'] ?? ''))), 0, 180, 'UTF-8');
        $message = mb_substr(trim(strip_tags((string)($input['message'] ?? ''))), 0, 12000, 'UTF-8');
        if ($title === '' || $message === '') return $this->error('invalid_notification');
        try {
            $id = $this->notifications->createInboxNotification([
                'tenant_id' => $tenantID, 'user_id' => $userID,
                'type' => $this->token((string)($input['type'] ?? 'system'), 'system'),
                'importance' => in_array(($input['importance'] ?? ''), ['info', 'warning', 'danger'], true) ? $input['importance'] : 'info',
                'title' => $title, 'message' => $message,
                'action_url' => $this->url((string)($input['action_url'] ?? '')),
                'meta' => is_array($input['meta'] ?? null) ? $input['meta'] : [],
                'expires_at' => $this->date((string)($input['expires_at'] ?? '')),
            ]);
            return ['status' => 'success', 'code' => 'notification_created', 'data' => ['notification_id' => $id]];
        } catch (Exception $exception) {
            return $this->failure($exception, 'notification_create_failed');
        }
    }

    public function inbox(int $userID, int $limit = 20, ?int $tenantID = null): array
    {
        if ($userID <= 0) return $this->error('invalid_user');
        try {
            $items = $this->notifications->inbox($userID, $tenantID, max(1, min(80, $limit)));
            $unread = $this->notifications->unreadCount($userID, $tenantID);
            return ['status' => 'success', 'code' => 'notifications_loaded', 'data' => ['unread' => $unread, 'items' => $items]];
        } catch (Exception $exception) {
            return $this->failure($exception, 'notifications_load_failed');
        }
    }

    public function history(int $userID, int $page = 1, int $perPage = 20, bool $unreadOnly = false, ?int $tenantID = null): array
    {
        if ($userID <= 0) return $this->error('invalid_user');
        try {
            $result = $this->notifications->history($userID, $tenantID, max(1, $page), max(1, min(80, $perPage)), $unreadOnly);
            return ['status' => 'success', 'code' => 'notifications_loaded', 'data' => ['items' => $result['items'], 'unread' => $this->notifications->unreadCount($userID, $tenantID)], 'meta' => $result['meta']];
        } catch (Exception $exception) { return $this->failure($exception, 'notifications_load_failed'); }
    }

    public function markRead(int $notificationID, int $userID, ?int $tenantID = null): array
    {
        try {
            return $this->notifications->markRead($notificationID, $userID, $tenantID)
                ? ['status' => 'success', 'code' => 'notification_read', 'data' => ['notification_id' => $notificationID]]
                : $this->error('notification_not_found');
        } catch (Exception $exception) { return $this->failure($exception, 'notification_read_failed'); }
    }

    public function markAllRead(int $userID, ?int $tenantID = null): array
    {
        try {
            return ['status' => 'success', 'code' => 'notifications_read', 'data' => ['updated' => $this->notifications->markAllRead($userID, $tenantID)]];
        } catch (Exception $exception) { return $this->failure($exception, 'notifications_read_failed'); }
    }

    public function detail(int $notificationID, int $userID, ?int $tenantID = null): array
    {
        if ($notificationID <= 0 || $userID <= 0) return $this->error('notification_not_found');
        try {
            $item = $this->notifications->findNotification($notificationID, $userID, $tenantID);
            if ($item === null) return $this->error('notification_not_found');
            return ['status' => 'success', 'code' => 'notification_loaded', 'data' => ['notification' => $item]];
        } catch (Exception $exception) { return $this->failure($exception, 'notification_load_failed'); }
    }

    public function markUnread(int $notificationID, int $userID, ?int $tenantID = null): array
    {
        try {
            return $this->notifications->markUnread($notificationID, $userID, $tenantID)
                ? ['status' => 'success', 'code' => 'notification_unread', 'data' => ['notification_id' => $notificationID]]
                : $this->error('notification_not_found');
        } catch (Exception $exception) { return $this->failure($exception, 'notification_read_failed'); }
    }

    public function delete(int $notificationID, int $userID, ?int $tenantID = null): array
    {
        try {
            return $this->notifications->deleteForUser($notificationID, $userID, $tenantID)
                ? ['status' => 'success', 'code' => 'notification_deleted'] : $this->error('notification_not_found');
        } catch (Exception $exception) { return $this->failure($exception, 'notification_delete_failed'); }
    }

    public function cleanup(): array
    {
        try { return ['status' => 'success', 'code' => 'notifications_cleaned', 'data' => ['deleted' => $this->notifications->cleanupExpired(date('Y-m-d H:i:s'))]]; }
        catch (Exception $exception) { return $this->failure($exception, 'notifications_cleanup_failed'); }
    }

    protected function token(string $value, string $fallback): string { $value = preg_replace('/[^a-z0-9_.-]+/', '_', strtolower(trim($value))) ?: ''; return $value !== '' ? mb_substr($value, 0, 80) : $fallback; }
    protected function url(string $value): ?string { $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? ''); return preg_match('~^https?://[^\s]+$|^/(?!/)[^\s]*$~i', $value) === 1 ? mb_substr($value, 0, 255) : null; }
    protected function date(string $value): ?string { return $value !== '' && strtotime($value) !== false ? date('Y-m-d H:i:s', strtotime($value)) : null; }
    protected function error(string $code): array { return ['status' => 'error', 'code' => $code]; }
    protected function failure(Exception $exception, string $code): array { error_log('[GFrame Notifications] ' . $exception->getMessage()); return $this->error($code); }
}
