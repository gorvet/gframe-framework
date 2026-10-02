<?php

namespace GFrame\Tests;

use GFrame\Notifications\NotificationModel;
use GFrame\Notifications\Campaigns\CampaignModel;
use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NotificationModelSqliteTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testCampaignModelLoadsAndPaginatesWithoutOverridingOrmMethods(): void
    {
        define('DB_DEFAULT_CONNECTION', 'campaign_test');
        define('DB_CONNECTIONS', ['campaign_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection('campaign_test');
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql'));
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('root@example.test', 'test-only', 1, 'verify')");
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/modules/notification-campaigns/database/sqlite.sql'));
        $model = new CampaignModel();
        $empty = $model->paginateCampaigns(99, 20);
        self::assertSame([], $empty['data']);
        self::assertSame(1, $empty['meta']['page']);

        $campaign = ['tenant_id' => null, 'name' => 'Prueba', 'title' => 'Aviso', 'message' => 'Texto', 'channels_json' => '["inbox"]', 'status' => 'scheduled'];
        $first = $model->create($campaign, []);
        $second = $model->create(array_replace($campaign, ['status' => 'paused']), []);
        $tenant = $model->create(array_replace($campaign, ['tenant_id' => 3]), []);
        self::assertSame($first, (int)$model->findCampaign($first)['campaign_id']);
        self::assertNull($model->findCampaign($tenant));
        self::assertSame($tenant, (int)$model->findCampaign($tenant, 3)['campaign_id']);
        self::assertNull($model->findCampaign($first, 3));

        $list = $model->paginateCampaigns(1, 1);
        self::assertSame(2, $list['meta']['total']);
        self::assertSame(2, $list['meta']['total_pages']);
        self::assertSame($second, (int)$list['data'][0]['campaign_id']);
        $last = $model->paginateCampaigns(99, 1);
        self::assertSame(2, $last['meta']['page']);
        self::assertSame($first, (int)$last['data'][0]['campaign_id']);
        $filtered = $model->paginateCampaigns(1, 20, null, 'paused');
        self::assertSame(1, $filtered['meta']['total']);
        self::assertSame($second, (int)$filtered['data'][0]['campaign_id']);
        self::assertSame(1, $model->paginateCampaigns(1, 20, 3)['meta']['total']);

        require_once dirname(__DIR__) . '/resources/modules/notification-campaigns/application/app/controllers/notification-campaigns/CampaignController.php';
        $_SESSION['auth'] = ['id' => 1];
        $_REQUEST = ['page' => 99, 'status' => 'paused'];
        $controller = new \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController($model);
        $listResponse = new \ReflectionMethod($controller, 'listResponse');
        $listResponse->setAccessible(true);
        $response = $listResponse->invoke($controller);
        self::assertSame('success', $response['status']);
        self::assertSame('campaigns_loaded', $response['code']);
        self::assertSame(1, $response['meta']['page']);
        self::assertSame($second, (int)$response['data'][0]['campaign_id']);
        self::assertTrue($response['can_manage']);
        $_SESSION['auth'] = ['id' => 0];
        self::assertFalse($listResponse->invoke($controller)['can_manage']);
        $_GET = ['source' => $first];
        $recycled = $controller->new();
        self::assertSame('Aviso', $recycled['data']['campaign']['title']);
        self::assertArrayNotHasKey('campaign_id', $recycled['data']['campaign']);
        self::assertArrayNotHasKey('scheduled_at', $recycled['data']['campaign']);
        self::assertSame('scheduled', $model->findCampaign($first)['status']);
        $_GET = [];

        self::assertSame('campaign_updated', $model->updateCampaignContent($second, ['name' => 'Editada', 'title' => 'Nuevo', 'message' => 'Contenido'])['code']);
        self::assertSame('Editada', $model->findCampaign($second)['name']);
        self::assertSame('paused', $model->findCampaign($second)['status']);
        self::assertSame('success', $model->updateCampaignContent($first, ['name' => 'Programada editable'])['status']);
        self::assertNotSame('success', $model->updateCampaignContent($second, ['name' => 'Otro tenant'], 3)['status']);
        $recipientID = (int)$pdo->query("INSERT INTO notification_campaign_recipients (campaign_id, channel, recipient, status, created_at) VALUES ($second, 'inbox', '1', 'queued', CURRENT_TIMESTAMP) RETURNING recipient_id")->fetchColumn();
        self::assertGreaterThan(0, $recipientID);
        self::assertNotSame('success', $model->updateCampaignContent($second, ['name' => 'Ya procesada'])['status']);
        self::assertSame('Editada', $model->findCampaign($second)['name']);
    }

    #[RunInSeparateProcess]
    public function testExpiryIsFilteredBeforeLimitAndUnreadCountUsesWholeScope(): void
    {
        define('DB_DEFAULT_CONNECTION', 'notification_test');
        define('DB_CONNECTIONS', ['notification_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection('notification_test');
        self::assertInstanceOf(PDO::class, $pdo);
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/modules/notifications/database/sqlite.sql'));
        $model = new NotificationModel();
        for ($i = 0; $i < 25; $i++) {
            $model->createInboxNotification(['tenant_id' => 3, 'user_id' => 7, 'type' => 'system', 'importance' => 'info', 'title' => 'Aviso', 'message' => 'Texto']);
        }
        $expired = $model->createInboxNotification(['tenant_id' => 3, 'user_id' => 7, 'type' => 'system', 'importance' => 'info', 'title' => 'Caducado', 'message' => 'Texto', 'expires_at' => '2020-01-01 00:00:00']);
        self::assertCount(20, $model->inbox(7, 3, 20));
        self::assertSame(25, $model->unreadCount(7, 3));
        self::assertCount(5, $model->history(7, 3, 2, 20, false)['items']);
        self::assertFalse($model->markRead($expired, 7, 3));
        self::assertFalse($model->deleteForUser($expired, 7, 3));
        self::assertSame(0, $model->unreadCount(7, 4));
        self::assertNull($model->findNotification(1, 8, 3));
        self::assertNull($model->findNotification(1, 7, 4));
        self::assertNull($model->findNotification($expired, 7, 3));
        self::assertFalse($model->markUnread($expired, 7, 3));
        self::assertTrue($model->markRead(1, 7, 3));
        self::assertTrue($model->markUnread(1, 7, 3));
        self::assertSame(0, (int)$model->findNotification(1, 7, 3)['is_read']);
        self::assertNull($model->findNotification(1, 7, 3)['read_at']);
    }
}
