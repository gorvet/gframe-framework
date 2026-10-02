<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\Campaigns\Contracts\CampaignAudienceProvider;
use GFrame\Notifications\Campaigns\Contracts\CampaignRepository;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use Exception;
use InvalidArgumentException;

class CampaignService
{
    public function __construct(
        protected readonly CampaignRepository $campaigns,
        protected readonly NotificationQueueRepository $queue,
        protected readonly \CronTaskService $cron,
        protected readonly ?\GFrame\Notifications\Campaigns\Contracts\CampaignRecipientGuard $recipientGuard = null
    ) {
    }

    public function create(array $input, iterable|CampaignAudienceProvider $audience, ?int $tenantID = null, ?int $createdBy = null): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $title = trim((string)($input['title'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        $channels = array_values(array_unique(array_filter(array_map(static fn($value): string => strtolower(trim((string)$value)), (array)($input['channels'] ?? [])))));
        $scheduledAt = trim((string)($input['scheduled_at'] ?? ''));
        $recurrence = (string)($input['recurrence'] ?? 'once');
        $importance = (string)($input['importance'] ?? 'info');
        $actionURL = trim((string)($input['action_url'] ?? ''));
        $expiry = (int)($input['expires_after_days'] ?? 0);
        if (!in_array($recurrence, ['once', 'daily', 'weekly'], true) || !in_array($importance, ['info', 'warning', 'danger'], true) || $expiry < 0 || $expiry > 3650 || mb_strlen($actionURL) > 255 || !CampaignPlaceholders::validActionURL($actionURL)) return ['status' => 'error', 'code' => 'invalid_campaign_options'];
        if ($name === '' || $title === '' || $message === '' || $channels === []) return ['status' => 'error', 'code' => 'invalid_campaign'];
        foreach ($channels as $channel) if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,79}$/', $channel) !== 1) return ['status' => 'error', 'code' => 'invalid_campaign_channel'];
        if ($scheduledAt !== '' && strtotime($scheduledAt) === false) return ['status' => 'error', 'code' => 'invalid_campaign_schedule'];

        try {
            $source = $audience instanceof CampaignAudienceProvider ? $audience->recipients((array)($input['audience'] ?? [])) : $audience;
            $recipients = $this->normalizeRecipients($source, $channels);
            if ($recipients === []) return ['status' => 'error', 'code' => 'empty_campaign_audience'];
            $status = 'scheduled';
            $id = $this->campaigns->create([
                'tenant_id' => $tenantID, 'name' => $name, 'title' => $title, 'message' => $message,
                'audience_json' => json_encode((array)($input['audience'] ?? []), JSON_UNESCAPED_SLASHES),
                'template_id' => trim((string)($input['template_id'] ?? 'notification')),
                'channels_json' => json_encode($channels, JSON_UNESCAPED_SLASHES),
                'status' => $status, 'scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null, 'created_by' => $createdBy,
                'recurrence' => $recurrence, 'parent_id' => $input['parent_id'] ?? null, 'importance' => $importance, 'action_url' => $actionURL !== '' ? $actionURL : null, 'expires_after_days' => $expiry,
            ], $recipients);
            $runAt = $scheduledAt !== '' ? $scheduledAt : gmdate('Y-m-d H:i:s');
            $task = $this->cron->schedule('notification-campaign.' . $id, CampaignCronHandler::class, $runAt, ['campaign_id' => $id, 'tenant_id' => $tenantID], 60);
            if (($task['status'] ?? '') !== 'success') throw new InvalidArgumentException('No se pudo programar la campaña.');
            $data = ['campaign_id' => $id, 'recipients' => count($recipients)];
            if ($scheduledAt === '') {
                $data['dispatch'] = $this->dispatch($id, $tenantID);
                $remaining = (int)($data['dispatch']['data']['progress']['pending'] ?? 0) + (int)($data['dispatch']['data']['progress']['processing'] ?? 0);
                if ($remaining === 0) $this->cron->cancel('notification-campaign.' . $id);
            }
            return ['status' => 'success', 'code' => $scheduledAt === '' ? 'campaign_started' : 'campaign_scheduled', 'data' => $data];
        } catch (Exception $exception) {
            error_log('[GFrame Campaigns] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'campaign_create_failed'];
        }
    }

    public function dispatch(int $campaignID, ?int $tenantID = null, int $batch = 200): array
    {
        try {
            $campaign = $this->campaigns->findCampaign($campaignID, $tenantID);
            if ($campaign === null) return ['status' => 'error', 'code' => 'campaign_not_found'];
            if (!empty($campaign['parent_id'])) {
                $parent = $this->campaigns->findCampaign((int)$campaign['parent_id'], $tenantID);
                if (!$parent || $parent['status'] === 'cancelled') return ['status' => 'error', 'code' => 'campaign_parent_cancelled'];
                if ($parent['status'] === 'paused') return ['status' => 'error', 'code' => 'campaign_parent_paused'];
            }
            if (in_array((string)$campaign['status'], ['paused', 'cancelled', 'completed'], true)) return ['status' => 'error', 'code' => 'campaign_not_dispatchable'];
            $this->campaigns->updateStatus($campaignID, 'running', $tenantID);
            $this->campaigns->recoverRecipients($campaignID, 900);
            $queued = 0; $failed = 0;
            foreach ($this->campaigns->reserveRecipients($campaignID, $batch) as $recipient) {
                $jobs = 0;
                try {
                    if ($this->recipientGuard !== null && !$this->recipientGuard->allows($recipient, $tenantID)) {
                        $this->campaigns->markRecipientFailed((int)$recipient['recipient_id'], 'recipient_excluded');
                        $failed++;
                        continue;
                    }
                    $context = (array)($recipient['variables'] ?? []);
                    $title = CampaignPlaceholders::render((string)$campaign['title'], $context);
                    $message = CampaignPlaceholders::render((string)$campaign['message'], $context);
                    $actionURL = CampaignPlaceholders::actionURL((string)($campaign['action_url'] ?? ''), $context);
                    $importance = (string)($campaign['importance'] ?? 'info');
                    $expiresAt = !empty($campaign['expires_after_days']) ? date('Y-m-d H:i:s', time() + (int)$campaign['expires_after_days'] * 86400) : null;
                    $this->queue->enqueue([
                            'tenant_id' => $tenantID, 'channel' => (string)$recipient['channel'], 'recipient' => (string)$recipient['recipient'],
                            'deduplication_key' => 'campaign:' . $campaignID . ':recipient:' . (int)$recipient['recipient_id'],
                            'payload' => ['campaign_id' => $campaignID, 'subject' => $title, 'title' => $title, 'message' => $message, 'importance' => $importance, 'action_url' => $actionURL, 'expires_at' => $expiresAt, 'template' => (string)($campaign['template_id'] ?? 'notification'), 'variables' => ['title' => $title, 'message' => $message, 'importance' => $importance, 'action_url' => $actionURL] + $context],
                        ]);
                    $jobs = 1;
                    $this->campaigns->markRecipientQueued((int)$recipient['recipient_id'], $jobs);
                    $queued++;
                } catch (Exception $exception) {
                    $this->campaigns->markRecipientFailed((int)$recipient['recipient_id'], $exception->getMessage());
                    $failed++;
                }
            }
            $progress = $this->campaigns->refreshProgress($campaignID);
            if ($progress['pending'] === 0 && $progress['processing'] === 0) $this->campaigns->updateStatus($campaignID, $failed > 0 && $queued === 0 ? 'failed' : 'completed', $tenantID);
            return ['status' => 'success', 'code' => 'campaign_batch_queued', 'data' => ['queued' => $queued, 'failed' => $failed, 'progress' => $progress]];
        } catch (Exception $exception) {
            error_log('[GFrame Campaign Dispatch] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'campaign_dispatch_failed'];
        }
    }

    public function pause(int $id, ?int $tenantID = null): array { return $this->control($id, 'pause', 'paused', 'campaign_paused', $tenantID); }
    public function resume(int $id, ?int $tenantID = null): array { return $this->control($id, 'resume', 'scheduled', 'campaign_resumed', $tenantID); }
    public function cancel(int $id, ?int $tenantID = null): array { return $this->control($id, 'cancel', 'cancelled', 'campaign_cancelled', $tenantID); }

    protected function control(int $id, string $action, string $status, string $code, ?int $tenantID): array
    {
        try {
            $campaign = $this->campaigns->findCampaign($id, $tenantID);
            if (!$campaign) return ['status' => 'error', 'code' => 'campaign_not_found'];
            $this->cron->{$action}('notification-campaign.' . $id);
            if (($campaign['recurrence'] ?? 'once') !== 'once') $this->cron->{$action}('campaign-recurrence.' . $id);
            return $this->status($id, $status, $code, $tenantID);
        } catch (Exception $exception) {
            error_log('[GFrame Campaigns] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'campaign_update_failed'];
        }
    }

    protected function status(int $id, string $status, string $code, ?int $tenantID): array
    {
        try { return $this->campaigns->updateStatus($id, $status, $tenantID) ? ['status' => 'success', 'code' => $code] : ['status' => 'error', 'code' => 'campaign_not_found']; }
        catch (Exception $exception) { error_log('[GFrame Campaigns] ' . $exception->getMessage()); return ['status' => 'error', 'code' => 'campaign_update_failed']; }
    }

    protected function normalizeRecipients(iterable $source, array $allowedChannels): array
    {
        $recipients = [];
        foreach ($source as $item) {
            $item = is_array($item) ? $item : ['recipient' => (string)$item];
            $mapped = (array)($item['recipients'] ?? []);
            if ($mapped !== []) {
                foreach ($mapped as $channel => $recipient) {
                    $channel = strtolower(trim((string)$channel)); $recipient = trim((string)$recipient);
                    if ($recipient === '' || !in_array($channel, $allowedChannels, true)) continue;
                    $recipients[$channel . ':' . strtolower($recipient)] = ['channel' => $channel, 'recipient' => $recipient, 'variables' => (array)($item['variables'] ?? [])];
                }
                continue;
            }
            $recipient = trim((string)($item['recipient'] ?? ''));
            $channel = strtolower(trim((string)($item['channel'] ?? (count($allowedChannels) === 1 ? $allowedChannels[0] : ''))));
            if ($recipient === '' || !in_array($channel, $allowedChannels, true)) continue;
            $recipients[$channel . ':' . strtolower($recipient)] = ['channel' => $channel, 'recipient' => $recipient, 'variables' => (array)($item['variables'] ?? [])];
        }
        return array_values($recipients);
    }
}
