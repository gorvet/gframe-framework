<?php

namespace GFrame\Notifications\Campaigns;

final class AutomaticCampaignDeliveryModel extends \ORM
{
    protected $table = 'notification_campaign_deliveries';
    protected $primaryKey = 'delivery_id';
    protected $fillable = ['delivery_id', 'scope_id', 'rule_key', 'user_id', 'event_id', 'source', 'notification_id', 'queued_at'];

    /** The unique rule/user row serializes automatic and manual sends. Call within a transaction. */
    public function claim(int $scopeID, string $key, int $userID, string $eventID, string $source, int $days): bool
    {
        $query = $this->reset()->where('scope_id', '=', $scopeID)->where('rule_key', '=', $key)->where('user_id', '=', $userID);
        $rows = $query->limit(1)->get();
        $changes = ['event_id' => $eventID, 'source' => $source, 'queued_at' => gmdate('Y-m-d H:i:s'), 'notification_id' => null];
        if ($rows === []) {
            (new self($changes + ['scope_id' => $scopeID, 'rule_key' => $key, 'user_id' => $userID]))->insert();
            return true;
        }
        $result = $this->reset()->where('delivery_id', '=', (int)$rows[0]['delivery_id'])
            ->where('queued_at', '<=', gmdate('Y-m-d H:i:s', time() - $days * 86400))->update($changes);
        return (int)($result['affected'] ?? 0) > 0;
    }

    public function attach(int $scopeID, string $key, int $userID, int $notificationID): void
    {
        $this->reset()->where('scope_id', '=', $scopeID)->where('rule_key', '=', $key)->where('user_id', '=', $userID)->update(['notification_id' => $notificationID]);
    }
}
