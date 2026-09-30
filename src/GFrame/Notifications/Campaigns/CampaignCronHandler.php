<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\NotificationQueueModel;

final class CampaignCronHandler extends \Cron
{
    protected string $name = 'notification-campaigns';

    public function handle(array $task = []): array
    {
        $result = (new CampaignService(new CampaignModel(), new NotificationQueueModel(), new \CronTaskService()))->dispatch(
            (int)($task['campaign_id'] ?? 0),
            isset($task['tenant_id']) ? (int)$task['tenant_id'] : null
        );
        $pending = (int)($result['data']['progress']['pending'] ?? 0) + (int)($result['data']['progress']['processing'] ?? 0);
        $result['reschedule'] = ($result['status'] ?? '') === 'success' && $pending > 0;
        return $result;
    }
}
