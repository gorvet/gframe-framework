<?php

namespace GFrame\Tests;

use GFrame\Notifications\NotificationModel;
use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NotificationModelSqliteTest extends TestCase
{
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
    }
}
