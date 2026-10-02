<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\NotificationQueueModel;

final class CampaignRecurrenceCronHandler extends \Cron
{
    protected string $name = 'campaign-recurrence';

    public function handle(array $task = []): array
    {
        $id = (int)($task['campaign_id'] ?? 0);
        $tenantID = !empty($task['tenant_id']) ? (int)$task['tenant_id'] : null;
        $model = new CampaignModel(); $plans = new CampaignRecurrenceModel();
        $parent = $model->findCampaign($id, $tenantID); $plan = $plans->plan($id);
        if (!$parent || !$plan || $parent['status'] === 'cancelled') return ['status' => 'success', 'code' => 'campaign_recurrence_stopped', 'reschedule' => false];
        if ($parent['status'] === 'paused' || $plan['next_at'] > gmdate('Y-m-d H:i:s')) return ['status' => 'success', 'code' => 'campaign_recurrence_waiting', 'reschedule' => true];
        $audience = json_decode($plan['audience_json'], true) ?: [];
        $service = new CampaignService($model, new NotificationQueueModel(), new \CronTaskService(), new CampaignUserAudience());
        $child = $model->occurrence($id, $plan['next_at'], $tenantID);
        if (!$child) {
            $input = array_intersect_key($parent, array_flip(['name', 'title', 'message', 'template_id', 'importance', 'action_url', 'expires_after_days']));
            $input += ['parent_id' => $id, 'recurrence' => 'once', 'channels' => json_decode($parent['channels_json'], true) ?: [], 'scheduled_at' => $plan['next_at'], 'audience' => $audience];
            $result = $service->create($input, new CampaignUserAudience(), $tenantID, (int)$parent['created_by']);
            if (($result['code'] ?? '') === 'empty_campaign_audience') {
                $next = CampaignSchedule::next($parent['recurrence'], $plan['next_at']);
                while ($next <= gmdate('Y-m-d H:i:s')) $next = CampaignSchedule::next($parent['recurrence'], $next);
                $plans->advance($id, $plan['next_at'], $next);
                return ['status' => 'success', 'code' => 'campaign_recurrence_empty_audience', 'reschedule' => true];
            }
            $child = $model->occurrence($id, $plan['next_at'], $tenantID);
            if (!$child) return ['status' => 'error', 'code' => 'campaign_recurrence_failed', 'reschedule' => true];
        }
        if ($child['status'] !== 'completed') {
            $result = $service->dispatch((int)$child['campaign_id'], $tenantID);
            if (($result['status'] ?? '') !== 'success') return $result + ['reschedule' => true];
            if ((int)($result['data']['progress']['pending'] ?? 0) > 0 || (int)($result['data']['progress']['processing'] ?? 0) > 0) return $result + ['reschedule' => true];
        }
        $next = CampaignSchedule::next($parent['recurrence'], $plan['next_at']);
        while ($next <= gmdate('Y-m-d H:i:s')) $next = CampaignSchedule::next($parent['recurrence'], $next);
        $plans->advance($id, $plan['next_at'], $next);
        return ['status' => 'success', 'code' => 'campaign_recurrence_processed', 'data' => ['campaign_id' => (int)$child['campaign_id'], 'next_at' => $next], 'reschedule' => true];
    }
}
