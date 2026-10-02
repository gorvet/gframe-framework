<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\NotificationQueueModel;

final class CampaignCronHandler extends \Cron
{
    protected string $name = 'notification-campaigns';

    public function handle(array $task = []): array
    {
        $campaign = (new CampaignModel())->findCampaign((int)($task['campaign_id'] ?? 0), isset($task['tenant_id']) ? (int)$task['tenant_id'] : null);
        if ($campaign && !empty($campaign['scheduled_at']) && $campaign['scheduled_at'] > gmdate('Y-m-d H:i:s')) return ['status' => 'success', 'code' => 'campaign_waiting', 'reschedule' => true];
        $result = (new CampaignService(new CampaignModel(), new NotificationQueueModel(), new \CronTaskService(), new CampaignUserAudience()))->dispatch(
            (int)($task['campaign_id'] ?? 0),
            isset($task['tenant_id']) ? (int)$task['tenant_id'] : null
        );
        $pending = (int)($result['data']['progress']['pending'] ?? 0) + (int)($result['data']['progress']['processing'] ?? 0);
        $result['reschedule'] = (($result['status'] ?? '') === 'success' && $pending > 0) || ($result['code'] ?? '') === 'campaign_parent_paused';
        return $result;
    }
}
