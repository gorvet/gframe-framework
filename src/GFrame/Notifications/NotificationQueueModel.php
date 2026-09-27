<?php

namespace GFrame\Notifications;

class NotificationQueueModel extends \ORM
{
    protected $table = 'notification_queue';
    protected $primaryKey = 'notification_id';
    protected $fillable = [
        'notification_id', 'tenant_id', 'channel', 'recipient', 'payload_json',
        'status', 'attempts', 'last_error', 'available_at', 'created_at', 'sent_at',
    ];

    public function enqueue(array $notification): int
    {
        return (int)(new static([
            'tenant_id' => $notification['tenant_id'] ?? null,
            'channel' => (string)($notification['channel'] ?? ''),
            'recipient' => (string)($notification['recipient'] ?? ''),
            'payload_json' => json_encode($notification['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]))->insert();
    }

    /** @return list<array<string, mixed>> */
    public function reserve(int $limit): array
    {
        $rows = $this->reset()
            ->where('status', '=', 'pending')
            ->where('available_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('notification_id', 'ASC')
            ->limit($limit)
            ->get();

        $reserved = [];
        foreach ($rows as $row) {
            $id = (int)($row['notification_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $this->reset()->where('notification_id', '=', $id)->where('status', '=', 'pending')->update([
                'status' => 'processing',
                'attempts' => (int)($row['attempts'] ?? 0) + 1,
            ]);
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
}
