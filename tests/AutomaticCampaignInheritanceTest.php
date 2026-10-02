<?php
namespace GFrame\Tests;

use GFrame\Notifications\Campaigns\AutomaticCampaignModel;
use GFrame\Notifications\Campaigns\AutomaticCampaignDispatcher;
use GFrame\Notifications\Campaigns\AutomaticCampaignCronHandler;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class ProjectInvoiceCampaignModel extends AutomaticCampaignModel
{
    public function definitions(): array
    {
        return parent::definitions() + ['project.invoice' => [
            'name' => 'Factura pendiente', 'title' => 'Factura {{invoice_id}}', 'message' => 'Hola {{user_name}}',
            'description' => '', 'periodic' => true, 'is_active' => 0, 'cooldown_days' => 7,
        ]];
    }

    public function eligibleUsers(string $key, int $scopeID = 0): array
    {
        return $key === 'project.invoice' ? [['user_id' => 1]] : parent::eligibleUsers($key, $scopeID);
    }

    public function eligible(string $key, array $user, array $context = [], ?int $tenantID = null): bool
    {
        return $key === 'project.invoice' ? $user['status'] === 'verify' : parent::eligible($key, $user, $context, $tenantID);
    }

    public function variables(string $key, array $user, array $context = [], ?int $tenantID = null): array
    {
        return $key === 'project.invoice' ? ['invoice_id' => 'F-42'] : parent::variables($key, $user, $context, $tenantID);
    }
}

class ProjectInvoiceCronHandler extends AutomaticCampaignCronHandler
{
    protected function model(): AutomaticCampaignModel { return new ProjectInvoiceCampaignModel(); }
}

final class AutomaticCampaignInheritanceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testProjectSubclassUsesTheSameQueueCooldownHistoryAndCron(): void
    {
        define('DB_DEFAULT_CONNECTION', 'inheritance_test');
        define('DB_CONNECTIONS', ['inheritance_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql'] as $file) $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/' . $file));
        $pdo->exec("INSERT INTO users(email,password,role_id,status) VALUES('ada@example.test','test',2,'verify')");
        $model = new ProjectInvoiceCampaignModel();
        self::assertCount(5, $model->rules());
        self::assertSame([['user_id' => 1]], $model->eligibleUsers('project.invoice'));
        self::assertSame('automatic_campaign_disabled', AutomaticCampaignDispatcher::emit('project.invoice', 1, 'invoice-42', [], null, false, $model)['code']);
        $model->saveRule(0, 'project.invoice', true, 'Factura {{invoice_id}}', 'Hola {{user_name}}');
        self::assertSame('automatic_campaign_queued', AutomaticCampaignDispatcher::emit('project.invoice', 1, 'invoice-42', [], null, false, $model)['code']);
        self::assertSame('Factura F-42', json_decode($pdo->query('SELECT payload_json FROM notification_queue')->fetchColumn(), true)['title']);
        self::assertSame('automatic_campaign_suppressed', AutomaticCampaignDispatcher::emit('project.invoice', 1, 'invoice-43', [], null, true, $model)['code']);
        self::assertSame('automatic_campaign_ineligible', AutomaticCampaignDispatcher::emit('project.invoice', 1, 'invoice-44', [], 3, true, $model)['code']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM notification_automatic_campaign_history')->fetchColumn());
        $result = (new ProjectInvoiceCronHandler())->handle();
        self::assertSame(1, $result['data']['suppressed']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        self::assertNull((new AutomaticCampaignModel())->definition('project.invoice'));
    }

    #[RunInSeparateProcess]
    public function testOldCallbackConfigurationIsNotLoadedOrDeleted(): void
    {
        $root = sys_get_temp_dir() . '/gframe-old-rules-' . bin2hex(random_bytes(6)) . '/';
        mkdir($root . 'config/notifications', 0777, true);
        define('ABSPATH', $root);
        $file = $root . 'config/notifications/automatic-campaigns.php';
        file_put_contents($file, '<?php throw new RuntimeException("Old callbacks must not execute");');
        try {
            self::assertCount(4, (new AutomaticCampaignModel())->definitions());
            self::assertFileExists($file);
            self::assertFalse(class_exists('GFrame\\Notifications\\Campaigns\\AutomaticCampaignRegistry'));
        } finally {
            unlink($file); rmdir($root . 'config/notifications'); rmdir($root . 'config'); rmdir($root);
        }
    }
}
