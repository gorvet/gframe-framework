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
    public function testRelativeTimeUsesCompactUnits(): void
    {
        $now = time();
        foreach ([1 => '1 s', 120 => '2 min', 7200 => '2 h', 518400 => '6 d'] as $age => $label) {
            self::assertSame($label, \GFrame\Notifications\NotificationTime::relative(date('Y-m-d H:i:s', $now - $age), $now));
        }
        self::assertSame('0 s', \GFrame\Notifications\NotificationTime::relative(date('Y-m-d H:i:s', $now + 60), $now));
        self::assertSame('', \GFrame\Notifications\NotificationTime::relative('invalid', $now));
    }
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testHeaderIndicatorIsAnchoredToIconAndDropdownHasItsOwnScrollArea(): void
    {
        define('site_url', 'https://example.test/');
        $root = dirname(__DIR__);
        \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['notifications'], $root);
        ob_start(); include $root . '/resources/modules/notifications/application/admin/notifications.php'; $html = (string)ob_get_clean();
        self::assertStringContainsString('class="gframe-notification-icon"', $html);
        self::assertStringNotContainsString('top-0 start-100', $html);
        self::assertStringContainsString('gframe-notifications-menu', $html);
        self::assertStringContainsString('data-bs-auto-close="outside"', $html);
        self::assertStringNotContainsString('data-notifications-mark-all', $html);
        $css = (string)file_get_contents($root . '/resources/modules/notifications/public/notifications.css');
        self::assertStringContainsString('position: absolute !important', $css);
        self::assertStringContainsString('position: fixed !important', $css);
        self::assertStringContainsString('overflow-y: auto', $css);
        self::assertStringContainsString('.tpl-admin #header .navbar > ul.navbar-nav', (string)file_get_contents($root . '/resources/modules/admin-panel/public/admin.css'));
    }

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

    public function testDetailAndUnreadChangesRespectUserTenantAndVisibility(): void
    {
        $repository = new InMemoryNotificationRepository();
        $service = new NotificationService($repository);
        $service->notify(7, ['title' => 'Aviso', 'message' => 'Contenido'], 3);
        self::assertSame('notification_not_found', $service->detail(1, 8, 3)['code']);
        self::assertSame('notification_not_found', $service->detail(1, 7, 4)['code']);
        self::assertSame('Contenido', $service->detail(1, 7, 3)['data']['notification']['message']);
        $service->markRead(1, 7, 3);
        self::assertSame(0, $service->inbox(7, 20, 3)['data']['unread']);
        self::assertSame('notification_not_found', $service->markUnread(1, 8, 3)['code']);
        self::assertSame('notification_unread', $service->markUnread(1, 7, 3)['code']);
        self::assertSame(1, $service->inbox(7, 20, 3)['data']['unread']);
        $service->delete(1, 7, 3);
        self::assertSame('notification_not_found', $service->detail(1, 7, 3)['code']);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testSummaryShowsUnreadDotAndOnlyReadStateMenu(): void
    {
        define('site_url', 'https://example.test/project/');
        $items = [['notification_id' => 2, 'title' => '<Aviso>', 'message' => '<script>alert(1)</script>', 'created_at' => '2026-09-30', 'importance' => 'danger', 'is_read' => 0]];
        ob_start(); include dirname(__DIR__) . '/resources/modules/notifications/application/app/views/notifications/_inbox.php'; $html = (string)ob_get_clean();
        self::assertStringContainsString('href="https://example.test/project/notifications/view?id=2"', $html);
        self::assertStringContainsString('notification-unread-dot', $html);
        self::assertStringContainsString('notification-importance-danger', $html);
        self::assertStringContainsString('data-notification-read', $html);
        self::assertStringNotContainsString('data-notification-delete', $html);
        self::assertStringNotContainsString('<script>', $html);
        $items[0]['is_read'] = 1;
        ob_start(); include dirname(__DIR__) . '/resources/modules/notifications/application/app/views/notifications/_inbox.php'; $html = (string)ob_get_clean();
        self::assertStringContainsString('data-notification-unread', $html);
        self::assertStringNotContainsString('notification-unread-dot', $html);
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
        include dirname(__DIR__) . '/resources/modules/notifications/application/app/views/notifications/_inbox.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('/notifications/view?id=2', $html);
        self::assertStringNotContainsString('href="/account"', $html);
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

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testHistoryUsesSharedHeadingAndPaginationOnlyForMultiplePages(): void
    {
        define('site_url', 'https://example.test/');
        $fixture = sys_get_temp_dir() . '/gframe-notifications-' . bin2hex(random_bytes(6)) . '/';
        $views = dirname(__DIR__) . '/resources/modules/notifications/application/';
        mkdir($fixture . 'app/views/notifications', 0777, true);
        copy($views . 'app/views/notifications/_inbox.php', $fixture . 'app/views/notifications/_inbox.php');
        copy($views . 'app/views/notifications/_detailModal.php', $fixture . 'app/views/notifications/_detailModal.php');
        define('ABSPATH', $fixture);
        \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['notifications'], $fixture);
        try {
            foreach ([0, 1, 2] as $pages) {
                $data = ['data' => ['history' => ['data' => ['items' => []], 'meta' => ['page' => 1, 'total_pages' => $pages]]]];
                ob_start(); include $views . 'app/views/notifications/notificationsIndex.php'; $html = (string)ob_get_clean();
                self::assertStringContainsString('<h1 class="me-3">Notificaciones</h1>', $html);
                self::assertStringNotContainsString('Consulta tus avisos', $html);
                self::assertStringNotContainsString('container py-', $html);
                self::assertStringNotContainsString('data-notifications-mark-all', $html);
                self::assertStringContainsString('data-notifications-filter="unread"', $html);
                self::assertSame($pages > 1, str_contains($html, 'all_items_pagination'));
            }
            $data = ['data' => ['notification' => ['notification_id' => 2, 'title' => '<Aviso>', 'message' => 'Texto completo', 'created_at' => '2026-09-30', 'action_url' => 'javascript:alert(1)']]];
            ob_start(); include $views . 'app/views/notifications/notificationsView.php'; $html = (string)ob_get_clean();
            self::assertStringContainsString('Texto completo', $html);
            self::assertStringNotContainsString('javascript:', $html);
            self::assertStringContainsString('&lt;Aviso&gt;', $html);
            require_once $views . 'app/controllers/notifications/NotificationController.php';
            $repository = new InMemoryNotificationRepository();
            $service = new NotificationService($repository);
            $service->notify(7, ['title' => 'Aviso', 'message' => 'Contenido completo', 'action_url' => '/account'], 3);
            $_SESSION['auth'] = ['id' => 7, 'tenant_id' => 3];
            $_POST = ['notification_id' => 1];
            $controller = new \GFrame\Modules\Notifications\Controllers\NotificationController($service);
            $modal = $controller->detail();
            self::assertSame('success', $modal['status']);
            self::assertSame(1, $repository->records[1]['is_read']);
            self::assertStringContainsString('id="notification-detail-modal"', $modal['html']);
            self::assertStringContainsString('Contenido completo', $modal['html']);
            self::assertStringContainsString('href="/account"', $modal['html']);
            $_SESSION['auth']['id'] = 8;
            self::assertSame('notification_not_found', $controller->detail()['code']);
        } finally {
            unlink($fixture . 'app/views/notifications/_inbox.php');
            unlink($fixture . 'app/views/notifications/_detailModal.php');
            rmdir($fixture . 'app/views/notifications'); rmdir($fixture . 'app/views'); rmdir($fixture . 'app'); rmdir($fixture);
        }
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
    public function markUnread(int $notificationID, int $userID, ?int $tenantID): bool { if (!$this->owns($notificationID, $userID, $tenantID)) return false; $this->records[$notificationID]['is_read'] = 0; return true; }
    public function findNotification(int $notificationID, int $userID, ?int $tenantID): ?array { foreach ($this->active($userID, $tenantID) as $row) if ((int)$row['notification_id'] === $notificationID) return $row; return null; }
    public function markAllRead(int $userID, ?int $tenantID): int { $count = 0; foreach ($this->records as &$row) if ($row['user_id'] === $userID && $row['tenant_id'] === $tenantID && empty($row['is_deleted']) && empty($row['is_read'])) { $row['is_read'] = 1; $count++; } return $count; }
    public function deleteForUser(int $notificationID, int $userID, ?int $tenantID): bool { if (!$this->owns($notificationID, $userID, $tenantID)) return false; $this->records[$notificationID]['is_deleted'] = 1; return true; }
    public function cleanupExpired(string $now): int { return 0; }
    private function owns(int $id, int $userID, ?int $tenantID): bool { return isset($this->records[$id]) && $this->records[$id]['user_id'] === $userID && $this->records[$id]['tenant_id'] === $tenantID; }
    private function active(int $userID, ?int $tenantID): array { return array_values(array_filter($this->records, static fn(array $row): bool => $row['user_id'] === $userID && $row['tenant_id'] === $tenantID && empty($row['is_deleted']) && (empty($row['expires_at']) || strtotime($row['expires_at']) > time()))); }
}
