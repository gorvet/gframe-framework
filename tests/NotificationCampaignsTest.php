<?php

namespace GFrame\Tests;

use GFrame\Notifications\Campaigns\CampaignService;
use GFrame\Notifications\Campaigns\Contracts\CampaignRepository;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use PHPUnit\Framework\TestCase;

final class NotificationCampaignsTest extends TestCase
{
    public function testEachRecipientReceivesResolvedTitleAndMessageOnBothChannels(): void
    {
        $queue = new MemoryCampaignQueue();
        $service = new CampaignService(new MemoryCampaignRepository(), $queue, new \CronTaskService(new MemoryCampaignCronRepository()));
        $response = $service->create(['name' => 'Saludo', 'title' => 'Hola {{user_name}}', 'message' => 'Tu correo: {{user_email}}', 'channels' => ['inbox', 'email']], [
            ['recipients' => ['inbox' => '1', 'email' => 'ada@example.test'], 'variables' => ['user_name' => 'Ada', 'user_email' => 'ada@example.test']],
            ['recipients' => ['inbox' => '2'], 'variables' => ['user_name' => 'Luis', 'user_email' => 'luis@example.test']],
        ]);
        self::assertSame('success', $response['status']);
        self::assertCount(3, $queue->jobs);
        foreach (array_values($queue->jobs) as $index => $job) {
            $name = $index < 2 ? 'Ada' : 'Luis';
            self::assertSame('Hola ' . $name, $job['payload']['title']);
            self::assertSame($job['payload']['title'], $job['payload']['subject']);
            self::assertStringNotContainsString('{{', $job['payload']['message']);
            self::assertSame($job['payload']['message'], $job['payload']['variables']['message']);
        }
    }

    public function testImmediateCampaignQueuesEachChannelRecipientOnce(): void
    {
        $campaigns = new MemoryCampaignRepository(); $queue = new MemoryCampaignQueue(); $cronRepository = new MemoryCampaignCronRepository();
        $service = new CampaignService($campaigns, $queue, new \CronTaskService($cronRepository));
        $result = $service->create([
            'name' => 'Aviso', 'title' => 'Mantenimiento', 'message' => 'Esta noche',
            'channels' => ['email', 'inbox'],
        ], [
            ['channel' => 'email', 'recipient' => 'user@example.com'],
            ['channel' => 'email', 'recipient' => 'USER@example.com'],
            ['channel' => 'inbox', 'recipient' => '42'],
        ], 3, 8);

        self::assertSame('campaign_started', $result['code']);
        self::assertCount(2, $queue->jobs);
        self::assertSame(['email', 'inbox'], array_column($queue->jobs, 'channel'));
        self::assertSame('completed', $campaigns->campaigns[1]['status']);
        self::assertNotNull($cronRepository->findByKey('notification-campaign.1'));
    }

    public function testScheduledCampaignDoesNotQueueBeforeCron(): void
    {
        $campaigns = new MemoryCampaignRepository(); $queue = new MemoryCampaignQueue();
        $service = new CampaignService($campaigns, $queue, new \CronTaskService(new MemoryCampaignCronRepository()));
        $result = $service->create(['name' => 'Agenda', 'title' => 'Recordatorio', 'message' => 'Mensaje', 'channels' => ['email'], 'scheduled_at' => '2030-01-01 10:00:00'], [['recipient' => 'user@example.com']], null, 1);
        self::assertSame('campaign_scheduled', $result['code']);
        self::assertSame([], $queue->jobs);
    }

    public function testPauseResumeAndCancelRemainTenantScoped(): void
    {
        $campaigns = new MemoryCampaignRepository(); $queue = new MemoryCampaignQueue(); $cron = new \CronTaskService(new MemoryCampaignCronRepository());
        $service = new CampaignService($campaigns, $queue, $cron);
        $service->create(['name' => 'A', 'title' => 'B', 'message' => 'C', 'channels' => ['inbox'], 'scheduled_at' => '2030-01-01 10:00:00'], [['recipient' => '4']], 9, 1);
        self::assertSame('campaign_not_found', $service->pause(1, 8)['code']);
        self::assertSame('campaign_paused', $service->pause(1, 9)['code']);
        self::assertSame('campaign_resumed', $service->resume(1, 9)['code']);
        self::assertSame('campaign_cancelled', $service->cancel(1, 9)['code']);
    }

    public function testModuleDependsOnNotificationsAndCronWithoutDependingOnEmail(): void
    {
        $module = require dirname(__DIR__) . '/resources/modules/notification-campaigns/module.php';
        self::assertContains('notifications', $module['dependencies']); self::assertContains('cron-runner', $module['dependencies']); self::assertNotContains('notifications-email', $module['dependencies']);
        foreach (glob(dirname(__DIR__) . '/src/GFrame/Notifications/Campaigns/*.php') ?: [] as $file) self::assertStringNotContainsString('Throwable', (string)file_get_contents($file), $file);
    }
}

final class MemoryCampaignRepository implements CampaignRepository
{
    public array $campaigns = []; public array $recipients = [];
    public function create(array $campaign, array $recipients): int { $id = count($this->campaigns) + 1; $this->campaigns[$id] = ['campaign_id' => $id] + $campaign; foreach ($recipients as $recipient) { $rid = count($this->recipients) + 1; $this->recipients[$rid] = ['recipient_id' => $rid, 'campaign_id' => $id, 'status' => 'pending'] + $recipient; } return $id; }
    public function findCampaign(int $campaignID, ?int $tenantID = null): ?array { $row = $this->campaigns[$campaignID] ?? null; return $row !== null && ($row['tenant_id'] ?? null) === $tenantID ? $row : null; }
    public function paginateCampaigns(int $page, int $perPage, ?int $tenantID = null, string $status = 'all'): array { return ['data' => array_values($this->campaigns), 'meta' => []]; }
    public function updateStatus(int $campaignID, string $status, ?int $tenantID = null): bool { if ($this->findCampaign($campaignID, $tenantID) === null) return false; $this->campaigns[$campaignID]['status'] = $status; return true; }
    public function reserveRecipients(int $campaignID, int $limit): array { $rows = []; foreach ($this->recipients as &$row) if ($row['campaign_id'] === $campaignID && $row['status'] === 'pending') { $row['status'] = 'processing'; $rows[] = $row; } return array_slice($rows, 0, $limit); }
    public function recoverRecipients(int $campaignID, int $seconds): int { return 0; }
    public function markRecipientQueued(int $recipientID, int $jobs): void { $this->recipients[$recipientID]['status'] = 'queued'; }
    public function markRecipientFailed(int $recipientID, string $error): void { $this->recipients[$recipientID]['status'] = 'failed'; }
    public function refreshProgress(int $campaignID): array { $result = ['total' => 0, 'pending' => 0, 'processing' => 0, 'queued' => 0, 'failed' => 0]; foreach ($this->recipients as $row) if ($row['campaign_id'] === $campaignID) { $result['total']++; $result[$row['status']]++; } return $result; }
}

final class MemoryCampaignQueue implements NotificationQueueRepository
{
    public array $jobs = [];
    public function enqueue(array $notification): int { $this->jobs[] = $notification; return count($this->jobs); }
    public function reserve(int $limit, ?string $channel = null): array { return []; }
    public function markSent(int $notificationID): void {}
    public function markFailed(int $notificationID, string $error): void {}
    public function releaseForRetry(int $notificationID, string $error, string $availableAt): void {}
}

final class MemoryCampaignCronRepository implements \CronTaskRepository
{
    public array $tasks = [];
    public function createTask(array $task): int { $id = count($this->tasks) + 1; $this->tasks[$id] = ['task_id' => $id] + $task; return $id; }
    public function findByKey(string $taskKey): ?array { foreach ($this->tasks as $task) if ($task['task_key'] === $taskKey) return $task; return null; }
    public function updateTask(string $taskKey, array $changes): bool { foreach ($this->tasks as &$task) if ($task['task_key'] === $taskKey) { $task = $changes + $task; return true; } return false; }
    public function reserveDue(int $limit): array { return []; }
    public function markSuccess(int $taskID, ?string $nextRunAt = null): void {}
    public function markFailed(int $taskID, string $message): void {}
    public function recoverStale(int $seconds): int { return 0; }
}
