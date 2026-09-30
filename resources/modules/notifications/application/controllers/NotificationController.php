<?php

use GFrame\Notifications\NotificationModel;
use GFrame\Notifications\NotificationService;

final class NotificationController
{
    use HeartbeatChannelTrait;
    private NotificationService $notifications;

    public function __construct(?NotificationService $notifications = null)
    {
        $this->notifications = $notifications ?? new NotificationService(new NotificationModel());
    }

    public function inbox(): array { return $this->withHtml($this->notifications->inbox($this->userID(), (int)($_POST['limit'] ?? 20), $this->tenantID())); }
    public function index(): array { return ['status' => 'success', 'code' => 'notifications_page_loaded', 'data' => ['history' => $this->notifications->history($this->userID(), max(1, (int)($_GET['page'] ?? 1)), 20, ($_GET['filter'] ?? '') === 'unread', $this->tenantID())]]; }
    public function history(): array
    {
        $response = $this->notifications->history($this->userID(), (int)($_POST['page'] ?? 1), 20, ($_POST['filter'] ?? '') === 'unread', $this->tenantID());
        if (($response['status'] ?? '') !== 'success') return $response;
        $items = (array)($response['data']['items'] ?? []);
        $meta = (array)($response['meta'] ?? []);
        ob_start();
        include ABSPATH . 'app/views/notifications/_history.php';
        $response['html'] = (string)ob_get_clean();
        return $response;
    }
    public function heartbeatInboxChannel(array $payload = [], array $context = []): array { return $this->withHtml($this->notifications->inbox($this->userID(), $this->hbInt($payload, 'limit', 20, 1, 40), $this->tenantID())); }
    public function markRead(): array { return $this->notifications->markRead((int)($_POST['notification_id'] ?? 0), $this->userID(), $this->tenantID()); }
    public function markAllRead(): array { return $this->notifications->markAllRead($this->userID(), $this->tenantID()); }
    public function delete(): array { return $this->notifications->delete((int)($_POST['notification_id'] ?? 0), $this->userID(), $this->tenantID()); }

    private function withHtml(array $response): array
    {
        if (($response['status'] ?? '') !== 'success') return $response;
        $items = (array)($response['data']['items'] ?? []);
        ob_start();
        include ABSPATH . 'app/views/components/notifications/_inbox.php';
        $response['html'] = (string)ob_get_clean();
        return $response;
    }

    private function userID(): int { return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0); }
    private function tenantID(): ?int { $id = (int)($_SESSION['auth']['tenant_id'] ?? $_SESSION['tenant_id'] ?? $_SESSION['tenantID'] ?? 0); return $id > 0 ? $id : null; }
}
