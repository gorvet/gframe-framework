<?php

namespace GFrame\Tests;

use GFrame\Notifications\Contracts\NotificationRepository;
use GFrame\Notifications\Contracts\NotificationTransport;
use GFrame\Notifications\InboxNotificationTransport;
use GFrame\Notifications\NotificationService;
use GFrame\Notifications\NotificationTransportRegistry;
use PHPUnit\Framework\TestCase;

final class NotificationInboxTest extends TestCase
{
    public function testInboxLifecycleIsIsolatedByUserAndTenant(): void
    {
        $repository = new InMemoryNotificationRepository();
        $service = new NotificationService($repository);
        $created = $service->notify(7, ['title' => 'Aviso', 'message' => 'Contenido', 'meta' => ['source' => 'test']], 3);
        self::assertSame('success', $created['status']);
        self::assertSame(1, $service->inbox(7, 20, 3)['data']['unread']);
        self::assertSame([], $service->inbox(7, 20, 4)['data']['items']);
        self::assertSame('notification_not_found', $service->markRead(1, 8, 3)['code']);
        self::assertSame('success', $service->markRead(1, 7, 3)['status']);
        self::assertSame(0, $service->inbox(7, 20, 3)['data']['unread']);
        self::assertSame('success', $service->delete(1, 7, 3)['status']);
        self::assertSame([], $service->inbox(7, 20, 3)['data']['items']);
    }

    public function testUnreadCountIsNotLimitedByDropdownAndHistoryIsPaged(): void
    {
        $repository = new InMemoryNotificationRepository();
        $service = new NotificationService($repository);
        for ($i = 0; $i < 25; $i++) $service->notify(7, ['title' => 'Aviso', 'message' => 'Contenido'], 3);
        self::assertCount(20, $service->inbox(7, 20, 3)['data']['items']);
        self::assertSame(25, $service->inbox(7, 20, 3)['data']['unread']);
        $history = $service->history(7, 2, 20, false, 3);
        self::assertCount(5, $history['data']['items']);
        self::assertSame(2, $history['meta']['total_pages']);
        self::assertSame([], $service->history(7, 1, 20, false, 4)['data']['items']);
    }

    public function testUnsafeActionUrlIsNotPersistedAndSafeLinkIsRendered(): void
    {
        $repository = new InMemoryNotificationRepository();
        $service = new NotificationService($repository);
        $service->notify(3, ['title' => 'Uno', 'message' => 'Texto', 'action_url' => 'javascript:alert(1)']);
        self::assertNull($repository->records[1]['action_url']);
        $service->notify(3, ['title' => 'Dos', 'message' => 'Texto', 'action_url' => '/account']);
        $items = $service->inbox(3)['data']['items'];
        ob_start();
        include dirname(__DIR__) . '/resources/modules/notifications/application/views/_inbox.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('href="/account"', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testInboxIsARegisteredTransportAndOtherTransportsRemainAddons(): void
    {
        $repository = new InMemoryNotificationRepository();
        $registry = new NotificationTransportRegistry();
        $registry->register('inbox', new InboxNotificationTransport(new NotificationService($repository)));
        self::assertSame('success', $registry->dispatch('inbox', [
            'recipient' => 9, 'tenant_id' => 2, 'payload' => ['title' => 'Sistema', 'message' => 'Mensaje'],
        ])['status']);
        self::assertSame('notification_transport_not_registered', $registry->dispatch('email', [])['code']);
        self::assertCount(1, $repository->records);
    }

    public function testModuleContainsInboxWithoutCampaignEndpoints(): void
    {
        $root = dirname(__DIR__);
        $routes = (string)file_get_contents($root . '/resources/modules/notifications/application/routes/routes_ajax_notifications.php');
        self::assertStringContainsString('ajax/notifications/inbox', $routes);
        self::assertStringContainsString('mark-all-read', $routes);
        self::assertStringNotContainsString('campaign', $routes);
        foreach (glob($root . '/src/GFrame/Notifications/*.php') ?: [] as $file) {
            self::assertStringNotContainsString('Throwable', (string)file_get_contents($file), $file);
        }
    }
}

final class InMemoryNotificationRepository implements NotificationRepository
{
    public array $records = [];

    public function createInboxNotification(array $notification): int { $id = count($this->records) + 1; $this->records[$id] = ['notification_id' => $id, 'is_read' => 0, 'is_deleted' => 0, 'created_at' => date('Y-m-d H:i:s')] + $notification; return $id; }
    public function inbox(int $userID, ?int $tenantID, int $limit): array { return array_slice($this->active($userID, $tenantID), 0, $limit); }
    public function unreadCount(int $userID, ?int $tenantID): int { return count(array_filter($this->active($userID, $tenantID), static fn(array $row): bool => empty($row['is_read']))); }
    public function history(int $userID, ?int $tenantID, int $page, int $perPage, bool $unreadOnly): array { $rows = $this->active($userID, $tenantID); if ($unreadOnly) $rows = array_values(array_filter($rows, static fn(array $row): bool => empty($row['is_read']))); $total = count($rows); $pages = max(1, (int)ceil($total / $perPage)); $page = min(max(1, $page), $pages); return ['items' => array_slice($rows, ($page - 1) * $perPage, $perPage), 'meta' => ['page' => $page, 'total_pages' => $pages, 'total' => $total, 'per_page' => $perPage]]; }
    public function markRead(int $notificationID, int $userID, ?int $tenantID): bool { if (!$this->owns($notificationID, $userID, $tenantID)) return false; $this->records[$notificationID]['is_read'] = 1; return true; }
    public function markAllRead(int $userID, ?int $tenantID): int { $count = 0; foreach ($this->records as &$row) if ($row['user_id'] === $userID && $row['tenant_id'] === $tenantID && empty($row['is_deleted']) && empty($row['is_read'])) { $row['is_read'] = 1; $count++; } return $count; }
    public function deleteForUser(int $notificationID, int $userID, ?int $tenantID): bool { if (!$this->owns($notificationID, $userID, $tenantID)) return false; $this->records[$notificationID]['is_deleted'] = 1; return true; }
    public function cleanupExpired(string $now): int { return 0; }
    private function owns(int $id, int $userID, ?int $tenantID): bool { return isset($this->records[$id]) && $this->records[$id]['user_id'] === $userID && $this->records[$id]['tenant_id'] === $tenantID; }
    private function active(int $userID, ?int $tenantID): array { return array_values(array_filter($this->records, static fn(array $row): bool => $row['user_id'] === $userID && $row['tenant_id'] === $tenantID && empty($row['is_deleted']) && (empty($row['expires_at']) || strtotime($row['expires_at']) > time()))); }
}
