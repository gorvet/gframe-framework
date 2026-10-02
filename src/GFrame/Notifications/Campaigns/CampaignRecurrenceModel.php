<?php

namespace GFrame\Notifications\Campaigns;

final class CampaignRecurrenceModel extends \ORM
{
    protected $table = 'notification_campaign_recurrences';
    protected $primaryKey = 'campaign_id';
    protected $fillable = ['campaign_id', 'audience_json', 'next_at'];

    public function register(int $campaignID, array $audience, string $nextAt): array
    {
        (new self(['campaign_id' => $campaignID, 'audience_json' => json_encode($audience, JSON_UNESCAPED_SLASHES), 'next_at' => $nextAt]))->insert();
        return (new \CronTaskService())->schedule('campaign-recurrence.' . $campaignID, CampaignRecurrenceCronHandler::class, $nextAt, ['campaign_id' => $campaignID, 'tenant_id' => $audience['tenant_id'] ?? null], 60);
    }

    public function plan(int $campaignID): ?array { return $this->reset()->where('campaign_id', '=', $campaignID)->limit(1)->get()[0] ?? null; }
    public function configure(int $campaignID, array $audience, string $nextAt): array
    {
        if (!$this->plan($campaignID)) (new self(['campaign_id' => $campaignID, 'audience_json' => json_encode($audience), 'next_at' => $nextAt]))->insert();
        else $this->reset()->where('campaign_id', '=', $campaignID)->update(['audience_json' => json_encode($audience), 'next_at' => $nextAt]);
        $cron = new \CronTaskService();
        $key = 'campaign-recurrence.' . $campaignID;
        return (new \CronDataProvider())->findByKey($key) ? $cron->reschedule($key, $nextAt) : $cron->schedule($key, CampaignRecurrenceCronHandler::class, $nextAt, ['campaign_id' => $campaignID, 'tenant_id' => $audience['tenant_id'] ?? null], 60);
    }
    public function advance(int $campaignID, string $old, string $next): void { $this->reset()->where('campaign_id', '=', $campaignID)->where('next_at', '=', $old)->update(['next_at' => $next]); }
}
