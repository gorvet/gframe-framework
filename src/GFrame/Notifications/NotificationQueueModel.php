<?php

namespace GFrame\Notifications;

use GFrame\Notifications\Contracts\NotificationQueueRepository;
use Exception;

class NotificationQueueModel extends \ORM implements NotificationQueueRepository
{
    protected $table = 'notification_queue';
    protected $primaryKey = 'notification_id';
    protected $fillable = [
        'notification_id', 'tenant_id', 'channel', 'recipient', 'payload_json',
        'status', 'attempts', 'last_error', 'available_at', 'created_at', 'sent_at',
        'deduplication_key',
    ];

    public function enqueue(array $notification): int
    {
        $deduplicationKey = trim((string)($notification['deduplication_key'] ?? ''));
        if ($deduplicationKey !== '') {
            $existing = $this->reset()->select('notification_id')->where('deduplication_key', '=', $deduplicationKey)->limit(1)->get();
            if (isset($existing[0]['notification_id'])) return (int)$existing[0]['notification_id'];
        }
        $record = [
            'tenant_id' => $notification['tenant_id'] ?? null,
            'channel' => (string)($notification['channel'] ?? ''),
            'recipient' => (string)($notification['recipient'] ?? ''),
            'payload_json' => json_encode($notification['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'deduplication_key' => $deduplicationKey !== '' ? $deduplicationKey : null,
        ];
        try {
            return (int)(new static($record))->insert();
        } catch (Exception $exception) {
            if ($deduplicationKey !== '') {
                $existing = $this->reset()->select('notification_id')->where('deduplication_key', '=', $deduplicationKey)->limit(1)->get();
                if (isset($existing[0]['notification_id'])) return (int)$existing[0]['notification_id'];
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function reserve(int $limit, ?string $channel = null): array
    {
        $query = $this->reset()
            ->where('status', '=', 'pending')
            ->where('available_at', '<=', date('Y-m-d H:i:s'));
        if ($channel !== null && trim($channel) !== '') {
            $query->where('channel', '=', strtolower(trim($channel)));
        }
        $rows = $query
            ->orderBy('notification_id', 'ASC')
            ->limit($limit)
            ->get();

        $reserved = [];
        foreach ($rows as $row) {
            $id = (int)($row['notification_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $claimed = $this->reset()->where('notification_id', '=', $id)->where('status', '=', 'pending')->update([
                'status' => 'processing',
                'attempts' => (int)($row['attempts'] ?? 0) + 1,
            ]);
            if ((int)($claimed['affected'] ?? 0) < 1) {
                continue;
            }
            $row['attempts'] = (int)($row['attempts'] ?? 0) + 1;
            $row['payload'] = json_decode((string)($row['payload_json'] ?? '{}'), true) ?: [];
            $reserved[] = $row;
        }

        return $reserved;
    }

    public function markSent(int $notificationID): void
    {
        $this->reset()->where('notification_id', '=', $notificationID)->update([
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);
    }

    public function markFailed(int $notificationID, string $error): void
    {
        $this->reset()->where('notification_id', '=', $notificationID)->update([
            'status' => 'failed',
            'last_error' => mb_substr($error, 0, 1000, 'UTF-8'),
        ]);
    }

    public function releaseForRetry(int $notificationID, string $error, string $availableAt): void
    {
        $this->reset()->where('notification_id', '=', $notificationID)->update([
            'status' => 'pending',
            'last_error' => mb_substr($error, 0, 1000, 'UTF-8'),
            'available_at' => $availableAt,
        ]);
    }

    public function paginateQueue(int $page = 1, int $perPage = 30, string $status = 'all'): array
    {
        $apply = static function (self $query) use ($status): self {
            if (in_array($status, ['pending', 'processing', 'sent', 'failed'], true)) {
                $query->where('status', '=', $status);
            }
            return $query;
        };
        $total = (int)$apply($this->reset())->count('*');
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);
        return [
            'data' => $apply($this->reset())->orderBy('notification_id', 'DESC')->paginate($page, $perPage),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages, 'status' => $status],
        ];
    }

    public function retry(int $notificationID): void
    {
        $this->reset()->where('notification_id', '=', $notificationID)->update([
            'status' => 'pending',
            'last_error' => null,
            'available_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteNotification(int $notificationID): void
    {
        $this->reset()->where('notification_id', '=', $notificationID)->deleteWhere();
    }
}
