<?php

namespace GFrame\Tests;

use GFrame\Notifications\Campaigns\CampaignModel;
use GFrame\Notifications\Campaigns\CampaignRecurrenceCronHandler;
use GFrame\Notifications\Campaigns\CampaignRecurrenceModel;
use GFrame\Notifications\Campaigns\CampaignSchedule;
use GFrame\Notifications\Campaigns\CampaignService;
use GFrame\Notifications\Campaigns\CampaignUserAudience;
use GFrame\Notifications\NotificationQueueModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CampaignRecurrenceTest extends TestCase
{
    private function database(): \PDO
    {
        define('DB_DEFAULT_CONNECTION', 'campaign_recurrence_test');
        define('DB_CONNECTIONS', ['campaign_recurrence_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql', 'resources/modules/cron-runner/database/sqlite.sql'] as $file) $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/' . $file));
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('root@example.test', 'test-only', 1, 'verify'), ('ada@example.test', 'test-only', 2, 'verify'), ('excluded@example.test', 'test-only', 2, 'disabled')");
        return $pdo;
    }

    #[RunInSeparateProcess]
    public function testRecurringAudienceIsRefreshedAndRetriesDoNotDuplicateOccurrences(): void
    {
        $pdo = $this->database();
        $audience = ['scope' => 'active', 'channels' => ['inbox'], 'site_url' => 'https://example.test'];
        $model = new CampaignModel();
        $service = new CampaignService($model, new NotificationQueueModel(), new \CronTaskService(), new CampaignUserAudience());
        $created = $service->create(['name' => 'Diaria', 'title' => 'Hola {{user_name}}', 'message' => 'Aviso', 'channels' => ['inbox'], 'audience' => $audience, 'recurrence' => 'daily', 'importance' => 'warning', 'action_url' => '/account', 'expires_after_days' => 3], new CampaignUserAudience(), null, 1);
        self::assertSame('success', $created['status']);
        $id = (int)$created['data']['campaign_id'];
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        $plans = new CampaignRecurrenceModel();
        $due = gmdate('Y-m-d H:i:s', time() - 1);
        self::assertSame('success', $plans->register($id, $audience, $due)['status']);
        $pdo->exec("UPDATE users SET status = 'disabled' WHERE user_id = 2; INSERT INTO users (email, password, role_id, status) VALUES ('new@example.test', 'test-only', 2, 'verify')");
        $handler = new CampaignRecurrenceCronHandler();
        $run = $handler->handle(['campaign_id' => $id]);
        self::assertSame('campaign_recurrence_processed', $run['code']);
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        $child = $model->occurrence($id, $due, null);
        self::assertNotNull($child);
        self::assertSame(1, $model->paginateCampaigns(1, 20)['meta']['total']);
        $jobs = $pdo->query('SELECT recipient, payload_json FROM notification_queue ORDER BY notification_id DESC LIMIT 2')->fetchAll(\PDO::FETCH_ASSOC);
        $recipients = array_column($jobs, 'recipient'); sort($recipients);
        self::assertSame(['1', '4'], $recipients);
        $payload = json_decode($jobs[0]['payload_json'], true);
        self::assertSame('warning', $payload['importance']);
        self::assertSame('https://example.test/account', $payload['action_url']);
        self::assertEqualsWithDelta(time() + 3 * 86400, strtotime($payload['expires_at'] . ' UTC'), 2);
        self::assertGreaterThan(gmdate('Y-m-d H:i:s'), $plans->plan($id)['next_at']);
        self::assertSame('campaign_recurrence_waiting', $handler->handle(['campaign_id' => $id])['code']);
        $plans->advance($id, $plans->plan($id)['next_at'], $due);
        self::assertSame('campaign_recurrence_processed', $handler->handle(['campaign_id' => $id])['code']);
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        self::assertSame('campaign_paused', $service->pause($id)['code']);
        self::assertSame(0, (int)$pdo->query("SELECT is_active FROM cron_tasks WHERE task_key = 'campaign-recurrence.$id'")->fetchColumn());
        self::assertSame('campaign_recurrence_waiting', $handler->handle(['campaign_id' => $id])['code']);
        self::assertSame('campaign_resumed', $service->resume($id)['code']);
        self::assertSame('campaign_cancelled', $service->cancel($id)['code']);
        self::assertFalse($handler->handle(['campaign_id' => $id])['reschedule']);
    }

    #[RunInSeparateProcess]
    public function testPreviewAndTestSendDoNotBroadcastOrCreateCampaigns(): void
    {
        $pdo = $this->database();
        define('site_url', 'https://example.test');
        $temporary = sys_get_temp_dir() . '/gframe-campaign-preview-' . bin2hex(random_bytes(6)) . '/';
        $folder = $temporary . 'app/views/admin/notifications/campaigns';
        mkdir($folder, 0777, true);
        copy(dirname(__DIR__) . '/resources/modules/notification-campaigns/application/app/views/notification-campaigns/_audiencePreview.php', $folder . '/_audiencePreview.php');
        define('ABSPATH', $temporary);
        \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['notification-campaigns'], defined('ABSPATH') ? ABSPATH : dirname(__DIR__));
        require_once dirname(__DIR__) . '/resources/modules/notification-campaigns/application/app/controllers/notification-campaigns/CampaignController.php';
        try {
            $_SESSION['auth'] = ['id' => 1];
            $_POST = ['audience' => 'manual', 'user_ids' => [2, 3]];
            $controller = new \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController();
            $preview = $controller->previewAudience();
            self::assertSame(1, $preview['data']['total']);
            self::assertStringContainsString('ada@example.test', $preview['html']);
            self::assertStringNotContainsString('excluded@example.test', $preview['html']);
            $_POST += ['title' => 'Hola {{user_name}}', 'message' => '{{user_email}}', 'channels' => ['inbox', 'email'], 'importance' => 'danger', 'action_url' => '{{dashboard_url}}', 'expires_after_days' => 2];
            self::assertSame(2, $controller->testSend()['data']['queued']);
            self::assertSame(['1', 'root@example.test'], $pdo->query('SELECT recipient FROM notification_queue ORDER BY notification_id')->fetchAll(\PDO::FETCH_COLUMN));
            $payload = json_decode($pdo->query('SELECT payload_json FROM notification_queue LIMIT 1')->fetchColumn(), true);
            self::assertSame('[Prueba] Hola root', $payload['title']);
            self::assertSame('https://example.test/admin', $payload['action_url']);
            self::assertSame('danger', $payload['importance']);
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM notification_campaigns')->fetchColumn());
        } finally {
            unlink($folder . '/_audiencePreview.php');
            foreach ([$folder, dirname($folder), dirname($folder, 2), dirname($folder, 3), dirname($folder, 4), rtrim($temporary, '/')] as $directory) rmdir($directory);
        }
    }

    public function testDailyAndWeeklySchedulingUsesOriginalUtcContract(): void
    {
        self::assertSame('2026-10-01 16:00:00', CampaignSchedule::utc('2026-10-01 12:00', 'America/Havana'));
        self::assertSame('2026-10-02 16:00:00', CampaignSchedule::next('daily', '2026-10-01 16:00:00'));
        self::assertSame('2026-10-08 16:00:00', CampaignSchedule::next('weekly', '2026-10-01 16:00:00'));
    }

    #[RunInSeparateProcess]
    public function testUpgradeAddsOptionsWithoutDiscardingExistingCampaigns(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE notification_queue (notification_id INTEGER PRIMARY KEY)');
        $root = dirname(__DIR__) . '/resources/modules/notification-campaigns/database/migrations/sqlite/';
        $pdo->exec((string)file_get_contents($root . '202609280004_notification_campaigns.sql'));
        $pdo->exec("INSERT INTO notification_campaigns (name, title, message, channels_json, status, created_at, updated_at) VALUES ('Original', 'Título', 'Texto', '[\"inbox\"]', 'completed', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        foreach (['202609300001_notification_campaign_rules.sql', '202609300002_campaign_cooldown.sql', '202609300003_account_deactivations.sql', '202609300004_campaign_options_recurrence.sql', '202609300005_campaign_edit_history.sql'] as $migration) $pdo->exec((string)file_get_contents($root . $migration));
        $old = $pdo->query('SELECT * FROM notification_campaigns')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('Original', $old['name']);
        self::assertSame('completed', $old['status']);
        self::assertSame('once', $old['recurrence']);
        self::assertSame('info', $old['importance']);
        self::assertNull($old['parent_id']);
        self::assertNull($old['audience_json']);
        self::assertSame(0, (int)$old['expires_after_days']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM notification_account_deactivations')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM notification_campaign_recurrences')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM notification_automatic_campaign_history')->fetchColumn());
    }
}
