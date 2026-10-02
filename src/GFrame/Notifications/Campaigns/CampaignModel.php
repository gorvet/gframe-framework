<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\Campaigns\Contracts\CampaignRepository;
use Exception;

class CampaignModel extends \ORM implements CampaignRepository
{
    protected $table = 'notification_campaigns';
    protected $primaryKey = 'campaign_id';
    protected $fillable = ['campaign_id', 'tenant_id', 'name', 'title', 'message', 'template_id', 'channels_json', 'status', 'scheduled_at', 'started_at', 'completed_at', 'created_by', 'created_at', 'updated_at', 'recurrence', 'parent_id', 'importance', 'action_url', 'expires_after_days', 'audience_json'];

    public function audienceFor(array $campaign): array
    {
        $saved = json_decode((string)($campaign['audience_json'] ?? ''), true) ?: [];
        if ($saved !== []) return $saved;
        $plan = (new CampaignRecurrenceModel())->plan((int)$campaign['campaign_id']);
        if ($plan) return json_decode($plan['audience_json'], true) ?: [];
        $rows = self::queryTable('notification_campaign_recipients')->where('campaign_id', '=', (int)$campaign['campaign_id'])->get();
        $ids = []; $scope = 'manual';
        foreach ($rows as $row) {
            $context = json_decode((string)($row['variables_json'] ?? '{}'), true) ?: [];
            $scope = $context['audience_scope'] ?? $scope;
            if (!empty($context['user_id'])) $ids[] = (int)$context['user_id'];
            elseif ($row['channel'] === 'inbox') $ids[] = (int)$row['recipient'];
        }
        return ['scope' => $scope, 'user_ids' => array_values(array_unique($ids)), 'channels' => json_decode($campaign['channels_json'], true) ?: []];
    }

    public function updateDefinition(int $id, array $fields, array $audience, iterable $users, ?int $tenantID = null): array
    {
        self::beginTransaction();
        try {
            $query = $this->reset()->where('campaign_id', '=', $id)->whereIn('status', ['draft', 'scheduled', 'paused', 'completed', 'failed']);
            $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
            $query->whereRaw('(recurrence <> ? OR (status IN (?, ?, ?) AND NOT EXISTS (SELECT 1 FROM notification_campaign_recipients WHERE notification_campaign_recipients.campaign_id = notification_campaigns.campaign_id AND notification_campaign_recipients.status <> ?)))', ['once', 'draft', 'scheduled', 'paused', 'pending']);
            $locked = $query->update(['updated_at' => gmdate('Y-m-d H:i:s')]);
            if ((int)($locked['matched'] ?? 0) < 1) { self::rollBack(); return ['status' => 'error', 'code' => 'campaign_not_editable']; }
            $campaign = $this->findCampaign($id, $tenantID);
            $progress = $this->refreshProgress($id);
            $initialPending = $progress['pending'] === $progress['total'];
            $changes = array_intersect_key($fields, array_flip(['name', 'title', 'message', 'action_url', 'importance', 'expires_after_days', 'recurrence', 'channels_json']));
            $changes['audience_json'] = json_encode($audience, JSON_UNESCAPED_SLASHES);
            if ($initialPending) {
                $recipients = [];
                foreach ($users as $user) foreach ($user['recipients'] as $channel => $recipient) $recipients[$channel . ':' . $recipient] = ['campaign_id' => $id, 'channel' => $channel, 'recipient' => $recipient, 'variables_json' => json_encode($user['variables'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'status' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s')];
                if ($recipients === []) { self::rollBack(); return ['status' => 'error', 'code' => 'empty_campaign_audience']; }
                $changes['scheduled_at'] = $fields['scheduled_at'];
                self::queryTable('notification_campaign_recipients')->where('campaign_id', '=', $id)->deleteWhere();
                foreach ($recipients as $recipient) (new CampaignRecipientModel($recipient))->insert();
            }
            $this->reset()->where('campaign_id', '=', $id)->update($changes);
            $cron = new \CronTaskService();
            if ($initialPending) {
                $key = 'notification-campaign.' . $id; $date = $fields['scheduled_at'] ?? gmdate('Y-m-d H:i:s');
                $task = (new \CronDataProvider())->findByKey($key) ? $cron->reschedule($key, $date) : $cron->schedule($key, CampaignCronHandler::class, $date, ['campaign_id' => $id, 'tenant_id' => $tenantID], 60);
                if (($task['status'] ?? '') !== 'success') throw new \RuntimeException('Campaign reschedule failed');
                if ($campaign['status'] === 'paused') $cron->pause('notification-campaign.' . $id);
            }
            $plans = new CampaignRecurrenceModel();
            if ($fields['recurrence'] === 'once') {
                $plans->reset()->where('campaign_id', '=', $id)->deleteWhere();
                $cron->cancel('campaign-recurrence.' . $id);
            } else {
                $date = $fields['scheduled_at'] ?? gmdate('Y-m-d H:i:s');
                $next = $initialPending ? CampaignSchedule::next($fields['recurrence'], $date) : $date;
                $task = $plans->configure($id, $audience, $next);
                if (($task['status'] ?? '') !== 'success') throw new \RuntimeException('Recurrence reschedule failed');
                if ($campaign['status'] === 'paused') $cron->pause('campaign-recurrence.' . $id);
            }
            self::commit();
            return ['status' => 'success', 'code' => 'campaign_updated', 'data' => ['dispatch_now' => $initialPending && $fields['scheduled_at'] === null && $campaign['status'] !== 'paused']];
        } catch (Exception $exception) {
            self::rollBack(); error_log('[GFrame Campaign Edit] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'campaign_update_failed'];
        }
    }

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

    public function findCampaign(int $campaignID, ?int $tenantID = null): ?array
    {
        $query = $this->reset()->where('campaign_id', '=', $campaignID);
        $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
        $rows = $query->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function paginateCampaigns(int $page, int $perPage, ?int $tenantID = null, string $status = 'all'): array
    {
        $apply = static function (self $query) use ($tenantID, $status): self {
            $query->whereNull('parent_id');
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

    public function updateCampaignContent(int $campaignID, array $fields, ?int $tenantID = null): array
    {
        try {
            $query = $this->reset()->where('campaign_id', '=', $campaignID)->whereIn('status', ['draft', 'scheduled', 'paused', 'completed', 'failed']);
            $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
            $query->whereRaw('(recurrence <> ? OR (status <> ? AND NOT EXISTS (SELECT 1 FROM notification_campaign_recipients WHERE notification_campaign_recipients.campaign_id = notification_campaigns.campaign_id AND notification_campaign_recipients.status <> ?)))', ['once', 'completed', 'pending']);
            $changes = array_intersect_key($fields, array_flip(['name', 'title', 'message', 'action_url', 'importance', 'expires_after_days']));
            $changes['updated_at'] = gmdate('Y-m-d H:i:s');
            $result = $query->update($changes);
            if (!in_array($result['status'] ?? '', ['updated', 'no_change'], true)) return ['status' => 'error', 'code' => 'campaign_update_failed'];
            return (int)($result['matched'] ?? 0) > 0 ? ['status' => 'success', 'code' => 'campaign_updated'] : ['status' => 'error', 'code' => 'campaign_not_editable'];
        } catch (Exception $exception) {
            error_log('[GFrame Campaign Update] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'campaign_update_failed'];
        }
    }

    public function occurrence(int $parentID, string $date, ?int $tenantID): ?array
    {
        $query = $this->reset()->where('parent_id', '=', $parentID)->where('scheduled_at', '=', $date);
        $tenantID === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', '=', $tenantID);
        return $query->limit(1)->get()[0] ?? null;
    }
}

final class CampaignRecipientModel extends \ORM
{
    protected $table = 'notification_campaign_recipients';
    protected $primaryKey = 'recipient_id';
    protected $fillable = ['recipient_id', 'campaign_id', 'channel', 'recipient', 'variables_json', 'status', 'queued_jobs', 'last_error', 'processing_at', 'queued_at', 'created_at'];
}
