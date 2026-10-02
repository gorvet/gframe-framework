<?php

namespace GFrame\Notifications\Campaigns;

final class AutomaticCampaignHistoryModel extends \ORM
{
    protected $table = 'notification_automatic_campaign_history';
    protected $primaryKey = 'history_id';
    protected $fillable = ['scope_id', 'run_key', 'rule_key', 'source', 'title', 'notification_id', 'user_id', 'queued_at'];

    public function record(int $scopeID, string $key, string $eventID, string $source, string $title, int $userID, int $notificationID): void
    {
        (new self(['scope_id' => $scopeID, 'run_key' => hash('sha256', $scopeID . ':' . $key . ':' . $eventID), 'rule_key' => $key, 'source' => $source, 'title' => $title, 'user_id' => $userID, 'notification_id' => $notificationID, 'queued_at' => gmdate('Y-m-d H:i:s')]))->insert();
    }

    public function listHistory(int $scopeID, int $page = 1, int $perPage = 20): array
    {
        $total = (int)$this->reset()->where('scope_id', '=', $scopeID)->count('DISTINCT run_key');
        $pages = max(1, (int)ceil($total / $perPage)); $page = min(max(1, $page), $pages);
        $rows = $this->reset()->select('run_key', 'MIN(rule_key) AS rule_key', 'MIN(source) AS source', 'MIN(title) AS title', 'MIN(queued_at) AS queued_at', 'COUNT(*) AS recipients', "SUM(CASE WHEN notification_queue.status = 'sent' THEN 1 ELSE 0 END) AS sent", "SUM(CASE WHEN notification_queue.status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->leftJoin('notification_queue', 'notification_automatic_campaign_history.notification_id', '=', 'notification_queue.notification_id')
            ->where('scope_id', '=', $scopeID)->groupBy('run_key')->orderBy('queued_at', 'DESC')->orderBy('run_key', 'DESC')->paginate($page, $perPage);
        return ['data' => $rows, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $pages]];
    }
}
