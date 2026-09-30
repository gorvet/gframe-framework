<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\Campaigns\Contracts\CampaignRepository;
use Exception;

final class CampaignModel extends \ORM implements CampaignRepository
{
    protected $table = 'notification_campaigns';
    protected $primaryKey = 'campaign_id';
    protected $fillable = ['campaign_id', 'tenant_id', 'name', 'title', 'message', 'template_id', 'channels_json', 'status', 'scheduled_at', 'started_at', 'completed_at', 'created_by', 'created_at', 'updated_at'];

    public function create(array $campaign, array $recipients): int
    {
        self::beginTransaction();
        try {
            $campaign['created_at'] = $campaign['updated_at'] = gmdate('Y-m-d H:i:s');
            $id = (int)(new static($campaign))->insert();
            foreach ($recipients as $recipient) {
                (new CampaignRecipientModel([
                    'campaign_id' => $id,
                    'channel' => (string)$recipient['channel'],
                    'recipient' => (string)$recipient['recipient'],
                    'variables_json' => json_encode((array)($recipient['variables'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s'),
                ]))->insert();
            }
            self::commit();
            return $id;
        } catch (Exception $exception) {
            self::rollBack();
            throw $exception;
        }
    }

    public function find(int $campaignID, ?int $tenantID = null): ?array
    {
        $query = $this->reset()->where('campaign_id', '=', $campaignID);
        $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
        $rows = $query->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function paginate(int $page, int $perPage, ?int $tenantID = null, string $status = 'all'): array
    {
        $apply = static function (self $query) use ($tenantID, $status): self {
            $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
            if (in_array($status, ['draft', 'scheduled', 'running', 'paused', 'completed', 'cancelled', 'failed'], true)) $query->where('status', '=', $status);
            return $query;
        };
        $total = (int)$apply($this->reset())->count('*');
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        return ['data' => $apply($this->reset())->orderBy('campaign_id', 'DESC')->paginate($page, $perPage), 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $pages, 'status' => $status]];
    }

    public function updateStatus(int $campaignID, string $status, ?int $tenantID = null): bool
    {
        $query = $this->reset()->where('campaign_id', '=', $campaignID);
        $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
        $changes = ['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($status === 'running') $changes['started_at'] = gmdate('Y-m-d H:i:s');
        if ($status === 'completed') $changes['completed_at'] = gmdate('Y-m-d H:i:s');
        $result = $query->update($changes);
        return (int)($result['matched'] ?? 0) > 0;
    }

    public function reserveRecipients(int $campaignID, int $limit): array
    {
        $rows = $this->reset()->queryTable('notification_campaign_recipients')->where('campaign_id', '=', $campaignID)->where('status', '=', 'pending')->orderBy('recipient_id', 'ASC')->limit(max(1, min(500, $limit)))->get();
        $reserved = [];
        foreach ($rows as $row) {
            $id = (int)$row['recipient_id'];
            $claimed = $this->reset()->queryTable('notification_campaign_recipients')->where('recipient_id', '=', $id)->where('status', '=', 'pending')->update(['status' => 'processing', 'processing_at' => gmdate('Y-m-d H:i:s')]);
            if ((int)($claimed['affected'] ?? 0) < 1) continue;
            $row['variables'] = json_decode((string)($row['variables_json'] ?? '{}'), true) ?: [];
            $reserved[] = $row;
        }
        return $reserved;
    }

    public function recoverRecipients(int $campaignID, int $seconds): int
    {
        $result = $this->reset()->queryTable('notification_campaign_recipients')->where('campaign_id', '=', $campaignID)->where('status', '=', 'processing')->where('processing_at', '<=', gmdate('Y-m-d H:i:s', time() - max(60, $seconds)))->update(['status' => 'pending', 'processing_at' => null]);
        return (int)($result['affected'] ?? 0);
    }

    public function markRecipientQueued(int $recipientID, int $jobs): void
    {
        $this->reset()->queryTable('notification_campaign_recipients')->where('recipient_id', '=', $recipientID)->update(['status' => 'queued', 'queued_jobs' => $jobs, 'queued_at' => gmdate('Y-m-d H:i:s'), 'last_error' => null]);
    }

    public function markRecipientFailed(int $recipientID, string $error): void
    {
        $this->reset()->queryTable('notification_campaign_recipients')->where('recipient_id', '=', $recipientID)->update(['status' => 'failed', 'last_error' => mb_substr($error, 0, 1000, 'UTF-8')]);
    }

    public function refreshProgress(int $campaignID): array
    {
        $rows = $this->reset()->queryTable('notification_campaign_recipients')->select('status')->where('campaign_id', '=', $campaignID)->get();
        $progress = ['total' => count($rows), 'pending' => 0, 'processing' => 0, 'queued' => 0, 'failed' => 0];
        foreach ($rows as $row) { $status = (string)($row['status'] ?? 'pending'); if (isset($progress[$status])) $progress[$status]++; }
        return $progress;
    }
}

final class CampaignRecipientModel extends \ORM
{
    protected $table = 'notification_campaign_recipients';
    protected $primaryKey = 'recipient_id';
    protected $fillable = ['recipient_id', 'campaign_id', 'channel', 'recipient', 'variables_json', 'status', 'queued_jobs', 'last_error', 'processing_at', 'queued_at', 'created_at'];
}
