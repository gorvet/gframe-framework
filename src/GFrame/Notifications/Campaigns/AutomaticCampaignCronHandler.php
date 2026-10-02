<?php

namespace GFrame\Notifications\Campaigns;

class AutomaticCampaignCronHandler extends \Cron
{
    protected string $name = 'automatic-campaigns';

    public function handle(array $task = []): array
    {
        $tenantID = !empty($task['tenant_id']) ? (int)$task['tenant_id'] : null;
        $model = $this->model();
        $counts = ['queued' => 0, 'suppressed' => 0, 'failed' => 0];
        foreach ($model->rules($tenantID ?? 0) as $rule) {
            if (empty($rule['is_active']) || empty($model->definition($rule['rule_key'])['periodic'])) continue;
            foreach ($model->eligibleUsers($rule['rule_key'], $tenantID ?? 0) as $user) {
                $result = AutomaticCampaignDispatcher::emit($rule['rule_key'], (int)$user['user_id'], 'periodic:' . gmdate('Y-m-d'), ['site_url' => (string)($task['site_url'] ?? '')], $tenantID, false, $model);
                $counts[($result['code'] ?? '') === 'automatic_campaign_queued' ? 'queued' : (($result['code'] ?? '') === 'automatic_campaign_suppressed' ? 'suppressed' : 'failed')]++;
            }
        }
        if ($tenantID === null) $counts['deactivations'] = (new AccountDeactivationLifecycle())->process((string)($task['site_url'] ?? ''))['data'];
        return ['status' => 'success', 'code' => 'automatic_campaigns_processed', 'data' => $counts, 'reschedule' => true];
    }

    protected function model(): AutomaticCampaignModel
    {
        return new AutomaticCampaignModel();
    }
}
