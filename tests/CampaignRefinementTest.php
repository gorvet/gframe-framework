<?php

namespace GFrame\Tests;

use GFrame\Notifications\Campaigns\AutomaticCampaignHistoryModel;
use GFrame\Notifications\Campaigns\AutomaticCampaignModel;
use GFrame\Notifications\Campaigns\CampaignModel;
use GFrame\Notifications\Campaigns\CampaignRecurrenceModel;
use GFrame\Notifications\Campaigns\CampaignService;
use GFrame\Notifications\Campaigns\CampaignUserAudience;
use GFrame\Notifications\NotificationQueueModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CampaignRefinementTest extends TestCase
{
    private function database(): \PDO
    {
        define('DB_DEFAULT_CONNECTION', 'campaign_refinement');
        define('DB_CONNECTIONS', ['campaign_refinement' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        define('site_url', 'https://example.test/');
        $pdo = \DatabaseManager::connection();
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql', 'resources/modules/cron-runner/database/sqlite.sql'] as $file) $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/' . $file));
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('root@example.test', 'test-only', 1, 'verify'), ('ada@example.test', 'test-only', 2, 'verify'), ('luis@example.test', 'test-only', 2, 'verify')");
        $_SESSION['auth'] = ['id' => 1];
        require_once dirname(__DIR__) . '/resources/modules/notification-campaigns/application/app/controllers/notification-campaigns/CampaignController.php';
        return $pdo;
    }

    #[RunInSeparateProcess]
    public function testSavingAutomaticRuleUpdatesAnExistingCronHandler(): void
    {
        $pdo = $this->database();
        (new \CronTaskService())->schedule('automatic-campaigns.0', \GFrame\Notifications\Campaigns\AutomaticCampaignCronHandler::class, gmdate('Y-m-d H:i:s'));
        $controller = new class extends \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController {
            protected function automaticCronHandler(): string { return 'App\\Services\\NotificationCampaigns\\AutomaticCron'; }
        };
        $_POST = ['rule_key' => 'account_suspended', 'title' => 'Aviso', 'message' => 'Cuenta suspendida', 'is_active' => 1];
        self::assertSame('campaign_rule_saved', $controller->saveAutomatic()['code']);
        self::assertSame('App\\Services\\NotificationCampaigns\\AutomaticCron', $pdo->query("SELECT handler_class FROM cron_tasks WHERE task_key = 'automatic-campaigns.0'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cron_tasks')->fetchColumn());
    }

    #[RunInSeparateProcess]
    public function testDirectAutomaticSendSelectsItsOwnAudienceAndRecordsAnImmutableBatch(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE users SET status = 'suspended' WHERE user_id IN (2, 3)");
        $rules = new AutomaticCampaignModel();
        $rules->saveRule(0, 'account_suspended', false, 'Aviso original', 'Hola {{user_name}}');
        $_POST = ['rule_key' => 'account_suspended', 'user_ids' => [1]];
        $controller = new \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController();
        self::assertSame(2, $controller->sendAutomatic()['data']['queued']);
        self::assertSame(['ada@example.test', 'luis@example.test'], $pdo->query('SELECT recipient FROM notification_queue ORDER BY recipient')->fetchAll(\PDO::FETCH_COLUMN));
        $history = new AutomaticCampaignHistoryModel();
        $list = $history->listHistory(0);
        self::assertSame(1, $list['meta']['total']);
        self::assertSame(2, (int)$list['data'][0]['recipients']);
        self::assertSame('manual', $list['data'][0]['source']);
        self::assertSame(0, (int)$list['data'][0]['sent']);
        $pdo->exec("UPDATE notification_queue SET status = 'sent'");
        self::assertSame(2, (int)$history->listHistory(0)['data'][0]['sent']);
        $rules->saveRule(0, 'account_suspended', false, 'Título modificado', 'Otro mensaje');
        self::assertSame('Aviso original', $history->listHistory(0)['data'][0]['title']);
        self::assertSame(2, $controller->sendAutomatic()['data']['suppressed']);
        self::assertSame(1, $history->listHistory(0)['meta']['total']);
        self::assertSame(0, $history->listHistory(99)['meta']['total']);
        $_POST = ['rule_key' => 'account_blocked'];
        self::assertSame(0, $controller->sendAutomatic()['data']['queued']);
    }

    #[RunInSeparateProcess]
    public function testScheduledCampaignCanEditDateAudienceChannelsAndFrequencyAtomically(): void
    {
        $pdo = $this->database();
        $model = new CampaignModel();
        $service = new CampaignService($model, new NotificationQueueModel(), new \CronTaskService(), new CampaignUserAudience());
        $audience = ['scope' => 'manual', 'user_ids' => [2], 'channels' => ['inbox'], 'site_url' => 'https://example.test'];
        $created = $service->create(['name' => 'Inicial', 'title' => 'Inicial', 'message' => 'Texto', 'channels' => ['inbox'], 'scheduled_at' => '2030-01-01 10:00:00', 'audience' => $audience], new CampaignUserAudience(), null, 1);
        $id = (int)$created['data']['campaign_id'];
        $_GET = ['id' => $id];
        $controller = new \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController();
        self::assertSame([2], $controller->edit()['data']['criteria']['user_ids']);
        $_POST = ['campaign_id' => $id, 'title' => 'Editada', 'message' => 'Nuevo texto', 'audience' => 'manual', 'user_ids' => [3], 'channels' => ['inbox', 'email'], 'scheduled_at' => '2030-02-01 12:00:00', 'user_timezone' => 'America/Havana', 'recurrence' => 'weekly'];
        self::assertSame('campaign_updated', $controller->update()['code']);
        $saved = $model->findCampaign($id);
        self::assertSame('2030-02-01 17:00:00', $saved['scheduled_at']);
        self::assertSame('weekly', $saved['recurrence']);
        self::assertSame(['inbox', 'email'], json_decode($saved['channels_json'], true));
        self::assertSame(['3', 'luis@example.test'], $pdo->query('SELECT recipient FROM notification_campaign_recipients ORDER BY recipient_id')->fetchAll(\PDO::FETCH_COLUMN));
        self::assertSame('2030-02-01 17:00:00', $pdo->query("SELECT scheduled_at FROM cron_tasks WHERE task_key = 'notification-campaign.$id'")->fetchColumn());
        self::assertSame('2030-02-08 17:00:00', (new CampaignRecurrenceModel())->plan($id)['next_at']);
        self::assertSame('campaign_waiting', (new \GFrame\Notifications\Campaigns\CampaignCronHandler())->handle(['campaign_id' => $id])['code']);
        $_SESSION['auth']['tenant_id'] = 99;
        self::assertSame('campaign_not_editable', $controller->update()['code']);
        self::assertSame('Editada', $model->findCampaign($id)['title']);
        unset($_SESSION['auth']['tenant_id']);
        $_POST['recurrence'] = 'once'; $_POST['scheduled_at'] = ''; $_POST['user_timezone'] = 'UTC';
        self::assertSame('success', $controller->update()['status']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        self::assertNull((new CampaignRecurrenceModel())->plan($id));
        self::assertSame('completed', $model->findCampaign($id)['status']);
        self::assertSame('campaign_not_editable', $controller->update()['code']);
    }

    #[RunInSeparateProcess]
    public function testFailedRescheduleDoesNotLeaveChangedRecipientsOrContent(): void
    {
        $pdo = $this->database();
        $model = new CampaignModel();
        $service = new CampaignService($model, new NotificationQueueModel(), new \CronTaskService(), new CampaignUserAudience());
        $audience = ['scope' => 'manual', 'user_ids' => [2], 'channels' => ['inbox'], 'site_url' => 'https://example.test'];
        $id = (int)$service->create(['name' => 'Original', 'title' => 'Original', 'message' => 'Texto', 'channels' => ['inbox'], 'scheduled_at' => '2030-01-01 10:00:00', 'audience' => $audience], new CampaignUserAudience())['data']['campaign_id'];
        $pdo->exec("CREATE TRIGGER fail_cron_change BEFORE UPDATE ON cron_tasks BEGIN SELECT RAISE(ABORT, 'test-only cron failure'); END");
        $_POST = ['campaign_id' => $id, 'title' => 'Cambiada', 'message' => 'Otro texto', 'audience' => 'manual', 'user_ids' => [3], 'channels' => ['email'], 'scheduled_at' => '2030-02-01 10:00:00', 'recurrence' => 'once'];
        $log = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try { self::assertSame('campaign_update_failed', (new \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController())->update()['code']); }
        finally { ini_set('error_log', (string)$log); }
        self::assertSame('Original', $model->findCampaign($id)['title']);
        self::assertSame(['2'], $pdo->query('SELECT recipient FROM notification_campaign_recipients')->fetchAll(\PDO::FETCH_COLUMN));
    }

    #[RunInSeparateProcess]
    public function testEditingRecurrenceChangesFutureRunsWithoutRewritingQueuedMail(): void
    {
        $pdo = $this->database();
        $model = new CampaignModel();
        $service = new CampaignService($model, new NotificationQueueModel(), new \CronTaskService(), new CampaignUserAudience());
        $audience = ['scope' => 'manual', 'user_ids' => [2], 'channels' => ['inbox'], 'site_url' => 'https://example.test'];
        $id = (int)$service->create(['name' => 'Original', 'title' => 'Original', 'message' => 'Texto', 'channels' => ['inbox'], 'recurrence' => 'daily', 'audience' => $audience], new CampaignUserAudience())['data']['campaign_id'];
        (new CampaignRecurrenceModel())->register($id, $audience, '2030-01-01 10:00:00');
        $queued = $pdo->query('SELECT payload_json FROM notification_queue')->fetchColumn();
        $_POST = ['campaign_id' => $id, 'title' => 'Próxima', 'message' => 'Nuevo texto', 'audience' => 'manual', 'user_ids' => [3], 'channels' => ['email'], 'scheduled_at' => '2030-02-01 10:00:00', 'user_timezone' => 'UTC', 'recurrence' => 'weekly'];
        $controller = new \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController();
        self::assertSame('campaign_updated', $controller->update()['code']);
        self::assertSame($queued, $pdo->query('SELECT payload_json FROM notification_queue')->fetchColumn());
        self::assertSame(['2'], $pdo->query('SELECT recipient FROM notification_campaign_recipients')->fetchAll(\PDO::FETCH_COLUMN));
        $plan = (new CampaignRecurrenceModel())->plan($id);
        self::assertSame('2030-02-01 10:00:00', $plan['next_at']);
        self::assertSame([3], json_decode($plan['audience_json'], true)['user_ids']);
        $_GET = ['id' => $id];
        self::assertSame('2030-02-01 10:00:00', $controller->edit()['data']['campaign']['scheduled_at']);
        self::assertSame('completed', $model->findCampaign($id)['status']);
    }
}
