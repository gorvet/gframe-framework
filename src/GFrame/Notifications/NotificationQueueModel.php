<?php

namespace GFrame\Notifications;

use GFrame\Notifications\Contracts\LeasedNotificationQueueRepository;
use GFrame\Config\ConfigRepository;
use Exception;

class NotificationQueueModel extends \ORM implements LeasedNotificationQueueRepository
{
    private array $reservations = [];
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
        $this->recoverExpiredReservations($channel);
        $query = $this->reset()
            ->where('status', '=', 'pending')
            ->where('available_at', '<=', date('Y-m-d H:i:s'));
        if ($channel !== null && trim($channel) !== '') {
            $query->where('channel', '=', strtolower(trim($channel)));
        }
        $rows = $query
            ->orderBy('notification_id', 'ASC')
            ->limit(max(1, $limit))
            ->get();

        $reserved = [];
        foreach ($rows as $row) {
            $id = (int)($row['notification_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $expiresAt = $this->reservationExpiry();
            $claimed = $this->reset()->where('notification_id', '=', $id)->where('status', '=', 'pending')->where('attempts', '=', (int)($row['attempts'] ?? 0))->where('available_at', '<=', date('Y-m-d H:i:s'))->update([
                'status' => 'processing',
                'attempts' => (int)($row['attempts'] ?? 0) + 1,
                'available_at' => $expiresAt,
            ]);
            if ((int)($claimed['affected'] ?? 0) < 1) {
                continue;
            }
            $row['attempts'] = (int)($row['attempts'] ?? 0) + 1;
            $row['status'] = 'processing';
            $row['available_at'] = $expiresAt;
            $row['payload'] = json_decode((string)($row['payload_json'] ?? '{}'), true) ?: [];
            $this->reservations[$id] = $row;
            $reserved[] = $row;
        }

        return $reserved;
    }

    public function markSent(int $notificationID): void
    {
        $this->finishOwnedReservation($notificationID, 'sent');
    }

    public function markFailed(int $notificationID, string $error): void
    {
        $this->finishOwnedReservation($notificationID, 'failed', $error);
    }

    public function releaseForRetry(int $notificationID, string $error, string $availableAt): void
    {
        $this->finishOwnedReservation($notificationID, 'pending', $error, $availableAt);
    }

    public function recoverExpiredReservations(?string $channel = null): int
    {
        $query = $this->reset()->where('status', '=', 'processing')->where('available_at', '<=', date('Y-m-d H:i:s'));
        if ($channel !== null && trim($channel) !== '') $query->where('channel', '=', strtolower(trim($channel)));
        return (int)$query->update(['status' => 'pending'])['affected'];
    }

    public function renewReservation(array $notification): bool
    {
        $id = (int)($notification['notification_id'] ?? 0);
        $attempt = (int)($notification['attempts'] ?? 0);
        if ($id <= 0 || $attempt <= 0) return false;
        // Use PDO directly: matched counts from a separate ORM SELECT can race.
        $statement = $this->pdo()->prepare("UPDATE notification_queue SET available_at = ? WHERE notification_id = ? AND status = 'processing' AND attempts = ? AND available_at > ?");
        $expiry = $this->reservationExpiry();
        $statement->execute([$expiry, $id, $attempt, date('Y-m-d H:i:s')]);
        if ($statement->rowCount() > 0) return true;
        // MySQL may report zero when renewal occurs in the same second.
        $check = $this->pdo()->prepare("SELECT notification_id FROM notification_queue WHERE notification_id = ? AND status = 'processing' AND attempts = ? AND available_at = ? AND available_at > ?");
        $check->execute([$id, $attempt, $expiry, date('Y-m-d H:i:s')]);
        return $check->fetchColumn() !== false;
    }

    public function finishReservation(array $notification, string $status, ?string $error = null, ?string $availableAt = null): bool
    {
        if (!in_array($status, ['sent', 'failed', 'pending'], true)) throw new \InvalidArgumentException('Estado de finalización inválido.');
        $id = (int)($notification['notification_id'] ?? 0);
        $attempt = (int)($notification['attempts'] ?? 0);
        if ($id <= 0 || $attempt <= 0) return false;
        $statement = $this->pdo()->prepare("UPDATE notification_queue SET status = ?, last_error = ?, sent_at = ?, available_at = ? WHERE notification_id = ? AND status = 'processing' AND attempts = ? AND available_at > ?");
        $now = date('Y-m-d H:i:s');
        $statement->execute([$status, $error === null ? null : mb_substr($error, 0, 1000, 'UTF-8'), $status === 'sent' ? $now : null, $availableAt ?? $now, $id, $attempt, $now]);
        $finished = $statement->rowCount() > 0;
        if ($finished && (int)($this->reservations[$id]['attempts'] ?? 0) === $attempt) unset($this->reservations[$id]);
        return $finished;
    }

    private function finishOwnedReservation(int $notificationID, string $status, ?string $error = null, ?string $availableAt = null): void
    {
        if (!isset($this->reservations[$notificationID]) || !$this->finishReservation($this->reservations[$notificationID], $status, $error, $availableAt)) {
            throw new \RuntimeException('La reserva de notificación ya no pertenece a este worker.');
        }
        unset($this->reservations[$notificationID]);
    }

    private function reservationExpiry(): string
    {
        return date('Y-m-d H:i:s', time() + max(60, (int)ConfigRepository::get('notifications.queue.lease_seconds', 900)));
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
