<?php

namespace GFrame\Modules\Notifications\Controllers;

use GFrame\Notifications\NotificationModel;
use GFrame\Notifications\NotificationService;

class NotificationController
{
    use \HeartbeatChannelTrait;
    protected NotificationService $notifications;
    protected bool $standardQueue;

    public function __construct(?NotificationService $notifications = null)
    {
        $this->standardQueue = $notifications === null;
        $this->notifications = $notifications ?? new NotificationService(new NotificationModel());
    }

    public function inbox(): array { $this->processInbox(); return $this->withHtml(($_POST['filter'] ?? '') === 'unread' ? $this->notifications->history($this->userID(), 1, (int)($_POST['limit'] ?? 20), true, $this->tenantID()) : $this->notifications->inbox($this->userID(), (int)($_POST['limit'] ?? 20), $this->tenantID())); }
    public function index(): array { $this->processInbox(); return ['status' => 'success', 'code' => 'notifications_page_loaded', 'data' => ['history' => $this->notifications->history($this->userID(), max(1, (int)($_GET['page'] ?? 1)), 20, ($_GET['filter'] ?? '') === 'unread', $this->tenantID())]]; }
    public function history(): array
    {
        $this->processInbox();
        $response = $this->notifications->history($this->userID(), (int)($_POST['page'] ?? 1), 20, ($_POST['filter'] ?? '') === 'unread', $this->tenantID());
        if (($response['status'] ?? '') !== 'success') return $response;
        $items = (array)($response['data']['items'] ?? []);
        $meta = (array)($response['meta'] ?? []);
        ob_start();
        include $this->viewPath('_history.php');
        $response['html'] = (string)ob_get_clean();
        return $response;
    }
    public function heartbeatInboxChannel(array $payload = [], array $context = []): array { $this->processInbox(); return $this->withHtml($this->notifications->inbox($this->userID(), $this->hbInt($payload, 'limit', 20, 1, 40), $this->tenantID())); }

    protected function processInbox(): void { if ($this->standardQueue && $this->userID() > 0) (new \GFrame\Notifications\InboxQueueProcessor())->processNotificationBatch(120); }
    public function markRead(): array { return $this->notifications->markRead((int)($_POST['notification_id'] ?? 0), $this->userID(), $this->tenantID()); }
    public function markUnread(): array { return $this->notifications->markUnread((int)($_POST['notification_id'] ?? 0), $this->userID(), $this->tenantID()); }
    public function view(): array { return $this->notifications->detail((int)($_GET['id'] ?? 0), $this->userID(), $this->tenantID()); }
    public function detail(): array
    {
        $id = (int)($_POST['notification_id'] ?? 0);
        $response = $this->notifications->detail($id, $this->userID(), $this->tenantID());
        if (($response['status'] ?? '') !== 'success') return $response;
        $read = $this->notifications->markRead($id, $this->userID(), $this->tenantID());
        if (($read['status'] ?? '') !== 'success') return $read;
        $response['data']['notification']['is_read'] = 1;
        ob_start(); include $this->viewPath('_detailModal.php');
        $response['html'] = (string)ob_get_clean();
        return $response;
    }
    public function markAllRead(): array { return $this->notifications->markAllRead($this->userID(), $this->tenantID()); }
    public function delete(): array { return $this->notifications->delete((int)($_POST['notification_id'] ?? 0), $this->userID(), $this->tenantID()); }

    protected function withHtml(array $response): array
    {
        if (($response['status'] ?? '') !== 'success') return $response;
        $items = (array)($response['data']['items'] ?? []);
        ob_start();
        include $this->viewPath('_inbox.php');
        $response['html'] = (string)ob_get_clean();
        return $response;
    }

    protected function viewPath(string $name): string
    {
        $path = \GFrame\Modules\ModuleRuntime::file('views', 'notifications/' . $name, 'notifications');
        if ($path === null) throw new \RuntimeException('No se encontró la vista de Notificaciones.');
        return $path;
    }

    protected function userID(): int { return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0); }
    protected function tenantID(): ?int { $id = (int)($_SESSION['auth']['tenant_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['tenantID'] ?? 0); return $id > 0 ? $id : null; }
}
