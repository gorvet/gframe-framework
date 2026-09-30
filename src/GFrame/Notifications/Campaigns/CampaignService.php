<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\Campaigns\Contracts\CampaignAudienceProvider;
use GFrame\Notifications\Campaigns\Contracts\CampaignRepository;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use Exception;
use InvalidArgumentException;

final class CampaignService
{
    public function __construct(
        private readonly CampaignRepository $campaigns,
        private readonly NotificationQueueRepository $queue,
        private readonly \CronTaskService $cron
    ) {
    }

    public function create(array $input, iterable|CampaignAudienceProvider $audience, ?int $tenantID = null, ?int $createdBy = null): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $title = trim((string)($input['title'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        $channels = array_values(array_unique(array_filter(array_map(static fn($value): string => strtolower(trim((string)$value)), (array)($input['channels'] ?? [])))));
        $scheduledAt = trim((string)($input['scheduled_at'] ?? ''));
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
                'template_id' => trim((string)($input['template_id'] ?? 'notification')),
                'channels_json' => json_encode($channels, JSON_UNESCAPED_SLASHES),
                'status' => $status, 'scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null, 'created_by' => $createdBy,
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
            $campaign = $this->campaigns->find($campaignID, $tenantID);
            if ($campaign === null) return ['status' => 'error', 'code' => 'campaign_not_found'];
            if (in_array((string)$campaign['status'], ['paused', 'cancelled', 'completed'], true)) return ['status' => 'error', 'code' => 'campaign_not_dispatchable'];
            $this->campaigns->updateStatus($campaignID, 'running', $tenantID);
            $this->campaigns->recoverRecipients($campaignID, 900);
            $queued = 0; $failed = 0;
            foreach ($this->campaigns->reserveRecipients($campaignID, $batch) as $recipient) {
                $jobs = 0;
                try {
                    $this->queue->enqueue([
                            'tenant_id' => $tenantID, 'channel' => (string)$recipient['channel'], 'recipient' => (string)$recipient['recipient'],
                            'deduplication_key' => 'campaign:' . $campaignID . ':recipient:' . (int)$recipient['recipient_id'],
                            'payload' => ['campaign_id' => $campaignID, 'subject' => (string)$campaign['title'], 'title' => (string)$campaign['title'], 'message' => (string)$campaign['message'], 'template' => (string)($campaign['template_id'] ?? 'notification'), 'variables' => (array)($recipient['variables'] ?? []) + ['title' => (string)$campaign['title'], 'message' => (string)$campaign['message']]],
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

    public function pause(int $id, ?int $tenantID = null): array { $this->cron->pause('notification-campaign.' . $id); return $this->status($id, 'paused', 'campaign_paused', $tenantID); }
    public function resume(int $id, ?int $tenantID = null): array { $this->cron->resume('notification-campaign.' . $id); return $this->status($id, 'scheduled', 'campaign_resumed', $tenantID); }
    public function cancel(int $id, ?int $tenantID = null): array { $this->cron->cancel('notification-campaign.' . $id); return $this->status($id, 'cancelled', 'campaign_cancelled', $tenantID); }

    private function status(int $id, string $status, string $code, ?int $tenantID): array
    {
        try { return $this->campaigns->updateStatus($id, $status, $tenantID) ? ['status' => 'success', 'code' => $code] : ['status' => 'error', 'code' => 'campaign_not_found']; }
        catch (Exception $exception) { error_log('[GFrame Campaigns] ' . $exception->getMessage()); return ['status' => 'error', 'code' => 'campaign_update_failed']; }
    }

    private function normalizeRecipients(iterable $source, array $allowedChannels): array
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
