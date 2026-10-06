<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NotificationDocumentationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDocumentedCreationQueriesTransportAndInheritance(): void
    {
        define('DB_DEFAULT_CONNECTION', 'notification_docs');
        define('DB_CONNECTIONS', ['notification_docs' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $root = dirname(__DIR__);
        \DatabaseManager::connection('notification_docs')->exec(file_get_contents($root . '/resources/modules/notifications/database/sqlite.sql'));
        $source = file_get_contents($root . '/docs/notificaciones.md');
        preg_match_all('/```php\R(.*?)\R```/s', $source, $blocks);
        self::assertCount(5, $blocks[1]);
        $userID = 7;
        $tenantID = 3;
        eval($blocks[1][0]);
        self::assertSame('notification_created', $result['code']);
        $notificationID = $result['data']['notification_id'];
        eval($blocks[1][1]);
        self::assertSame(1, $unreadCount);
        self::assertCount(1, $items);
        self::assertSame('notification_read', $read['code']);
        self::assertSame('notification_unread', $unread['code']);
        self::assertSame([], $notifications->inbox($userID, 20, null)['data']['items']);
        eval($blocks[1][2]);
        self::assertSame('notification_dispatched', $result['code']);
        eval(preg_replace('/^<\?php\s*/', '', $blocks[1][3]));
        require_once $root . '/resources/modules/notifications/application/app/controllers/notifications/NotificationController.php';
        eval(preg_replace('/^<\?php\s*/', '', $blocks[1][4]));
        $custom = new \App\Services\Notifications\ProjectNotificationService(new \GFrame\Notifications\NotificationModel());
        self::assertSame('success', $custom->invoicePaid($userID, 42, $tenantID)['status']);
        self::assertInstanceOf(\GFrame\Modules\Notifications\Controllers\NotificationController::class, new \App\Controllers\Notifications\NotificationController());
    }
}
