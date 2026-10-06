<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CampaignDocumentationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAutomaticInheritanceAndRecurrenceExamples(): void
    {
        define('DB_DEFAULT_CONNECTION', 'campaign_extensions_docs');
        define('DB_CONNECTIONS', ['campaign_extensions_docs' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $root = dirname(__DIR__);
        $pdo = \DatabaseManager::connection();
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql', 'resources/modules/cron-runner/database/sqlite.sql'] as $file) {
            $pdo->exec(file_get_contents($root . '/' . $file));
        }
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('ana@example.test', 'test-only', 1, 'verify')");
        preg_match_all('/```php\R(.*?)\R```/s', file_get_contents($root . '/docs/notification-campaigns.md'), $blocks);
        foreach ([4, 5] as $index) eval(preg_replace('/^<\?php\s*/', '', $blocks[1][$index]));
        require_once $root . '/resources/modules/notification-campaigns/application/app/controllers/notification-campaigns/CampaignController.php';
        eval(preg_replace('/^<\?php\s*/', '', $blocks[1][6]));
        $model = new \App\Models\NotificationCampaigns\AutomaticCampaignModel();
        self::assertCount(5, $model->definitions());
        self::assertCount(1, $model->eligibleUsers('project.account_tips'));
        self::assertSame([], $model->eligibleUsers('project.account_tips', 8));
        $model->saveRule(0, 'project.account_tips', true, 'Consejos', 'Consulta {{tips_url}}');
        $result = (new \App\Services\NotificationCampaigns\AutomaticCampaignCronHandler())->handle(['site_url' => 'https://example.com']);
        self::assertSame(1, $result['data']['queued']);
        $payload = json_decode($pdo->query('SELECT payload_json FROM notification_queue')->fetchColumn(), true);
        self::assertSame('Consulta https://example.com/help/account', $payload['message']);
        self::assertInstanceOf(\GFrame\Modules\NotificationCampaigns\Controllers\CampaignController::class, new \App\Controllers\NotificationCampaigns\CampaignController());
        $tenantID = null;
        $createdBy = 1;
        eval($blocks[1][0]);
        eval($blocks[1][7]);
        self::assertSame('success', $registration['status']);
        $plan = (new \GFrame\Notifications\Campaigns\CampaignRecurrenceModel())->plan($created['data']['campaign_id']);
        self::assertSame(\GFrame\Notifications\Campaigns\CampaignSchedule::next('weekly', $firstAt), $plan['next_at']);
    }

    #[RunInSeparateProcess]
    public function testCreationAndControlExamplesRunWithStandardAudience(): void
    {
        define('DB_DEFAULT_CONNECTION', 'campaign_docs');
        define('DB_CONNECTIONS', ['campaign_docs' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $root = dirname(__DIR__);
        $pdo = \DatabaseManager::connection();
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql', 'resources/modules/cron-runner/database/sqlite.sql'] as $file) {
            $pdo->exec(file_get_contents($root . '/' . $file));
        }
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('ana@example.test', 'test-only', 1, 'verify')");
        $source = file_get_contents($root . '/docs/notification-campaigns.md');
        preg_match_all('/```php\R(.*?)\R```/s', $source, $blocks);
        $tenantID = null;
        $createdBy = 1;
        eval($blocks[1][0]);
        self::assertSame('campaign_started', $result['code']);
        self::assertSame(1, $result['data']['recipients']);
        $job = $pdo->query('SELECT payload_json FROM notification_queue')->fetchColumn();
        self::assertSame('Hola ana, tendremos mantenimiento', json_decode($job, true)['title']);
        $campaignID = $result['data']['campaign_id'];
        eval($blocks[1][1]);
        self::assertSame('campaign_paused', $paused['code']);
        self::assertSame('campaign_resumed', $resumed['code']);
        self::assertSame('campaign_cancelled', $cancelled['code']);
        eval($blocks[1][2]);
        $audience = iterator_to_array((new \CustomerAudience())->recipients([]));
        self::assertSame('42', $audience[0]['recipients']['inbox']);
        self::assertSame('Ana', $audience[0]['variables']['user_name']);
    }
}
