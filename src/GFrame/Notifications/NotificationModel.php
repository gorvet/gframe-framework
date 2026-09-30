<?php

namespace GFrame\Notifications;

use GFrame\Notifications\Contracts\NotificationRepository;

class NotificationModel extends \ORM implements NotificationRepository
{
    protected $table = 'user_notifications';
    protected $primaryKey = 'notification_id';
    protected $fillable = [
        'notification_id', 'tenant_id', 'user_id', 'type', 'importance', 'title', 'message',
        'action_url', 'meta_json', 'is_read', 'read_at', 'is_deleted', 'deleted_at',
        'expires_at', 'created_at',
    ];

    public function createInboxNotification(array $notification): int
    {
        return (int)(new static([
            'tenant_id' => $notification['tenant_id'] ?? null,
            'user_id' => (int)$notification['user_id'],
            'type' => (string)$notification['type'],
            'importance' => (string)$notification['importance'],
            'title' => (string)$notification['title'],
            'message' => (string)$notification['message'],
            'action_url' => $notification['action_url'] ?? null,
            'meta_json' => json_encode($notification['meta'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_read' => 0, 'is_deleted' => 0,
            'expires_at' => $notification['expires_at'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]))->insert();
    }

    public function inbox(int $userID, ?int $tenantID, int $limit): array
    {
        $query = $this->active($userID, $tenantID)
            ->orderBy('notification_id', 'DESC')->limit($limit);
        return $this->cleanItems($query->get());
    }

    public function unreadCount(int $userID, ?int $tenantID): int
    {
        return (int)$this->active($userID, $tenantID)->where('is_read', '=', 0)->count('*');
    }

    public function history(int $userID, ?int $tenantID, int $page, int $perPage, bool $unreadOnly): array
    {
        $build = function () use ($userID, $tenantID, $unreadOnly): self {
            $query = $this->active($userID, $tenantID);
            if ($unreadOnly) $query->where('is_read', '=', 0);
            return $query;
        };
        $total = (int)$build()->count('*');
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = $build()->orderBy('notification_id', 'DESC')->paginate($page, $perPage);
        return ['items' => $this->cleanItems($items), 'meta' => ['page' => $page, 'total_pages' => $pages, 'total' => $total, 'per_page' => $perPage]];
    }

    private function cleanItems(array $items): array
    {
        foreach ($items as &$item) {
            $item['meta'] = json_decode((string)($item['meta_json'] ?? '{}'), true) ?: [];
            unset($item['meta_json'], $item['user_id'], $item['tenant_id'], $item['is_deleted'], $item['deleted_at']);
        }
        unset($item);
        return array_values($items);
    }

    public function markRead(int $notificationID, int $userID, ?int $tenantID): bool
    {
        $result = $this->active($userID, $tenantID)->where('notification_id', '=', $notificationID)
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
        return (int)($result['matched'] ?? $result['affected'] ?? 0) > 0;
    }

    public function markAllRead(int $userID, ?int $tenantID): int
    {
        $result = $this->active($userID, $tenantID)->where('is_read', '=', 0)
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
        return (int)($result['affected'] ?? 0);
    }

    public function deleteForUser(int $notificationID, int $userID, ?int $tenantID): bool
    {
        $now = date('Y-m-d H:i:s');
        $result = $this->active($userID, $tenantID)->where('notification_id', '=', $notificationID)
            ->update(['is_deleted' => 1, 'is_read' => 1, 'read_at' => $now, 'deleted_at' => $now]);
        return (int)($result['matched'] ?? $result['affected'] ?? 0) > 0;
    }

    public function cleanupExpired(string $now): int
    {
        $result = $this->reset()->where('is_deleted', '=', 0)->where('expires_at', '<=', $now)
            ->update(['is_deleted' => 1, 'deleted_at' => $now]);
        return (int)($result['affected'] ?? 0);
    }

    private function scope(self $query, ?int $tenantID): self
    {
        return $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
    }

    private function active(int $userID, ?int $tenantID): self
    {
        return $this->scope($this->reset()->where('user_id', '=', $userID)->where('is_deleted', '=', 0), $tenantID)
            ->whereRaw('(expires_at IS NULL OR expires_at > ?)', [date('Y-m-d H:i:s')]);
    }
}
