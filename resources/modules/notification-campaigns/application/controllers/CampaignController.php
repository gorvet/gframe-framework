<?php

use GFrame\Notifications\Campaigns\CampaignModel;
use GFrame\Notifications\Campaigns\CampaignService;
use GFrame\Notifications\NotificationQueueModel;

final class CampaignController
{
    private CampaignModel $model;
    private CampaignService $service;

    public function __construct(?CampaignModel $model = null, ?CampaignService $service = null)
    {
        $this->model = $model ?? new CampaignModel();
        $this->service = $service ?? new CampaignService($this->model, new NotificationQueueModel(), new CronTaskService());
    }

    public function index(): array { return $this->withHtml($this->listResponse()); }
    public function list(): array { return $this->withHtml($this->listResponse()); }

    public function create(): array
    {
        $channels = array_values(array_intersect(['inbox', 'email'], array_map('strval', (array)($_POST['channels'] ?? []))));
        $recipients = $this->parseRecipients((string)($_POST['recipients'] ?? ''), $channels);
        $response = $this->service->create([
            'name' => (string)($_POST['name'] ?? ''), 'title' => (string)($_POST['title'] ?? ''),
            'message' => (string)($_POST['message'] ?? ''), 'template_id' => (string)($_POST['template_id'] ?? 'notification'),
            'channels' => $channels, 'scheduled_at' => (string)($_POST['scheduled_at'] ?? ''),
        ], $recipients, $this->tenantID(), $this->userID());
        return $this->message($response);
    }

    public function action(): array
    {
        $id = (int)($_POST['campaign_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $response = match ($action) {
            'pause' => $this->service->pause($id, $this->tenantID()),
            'resume' => $this->service->resume($id, $this->tenantID()),
            'cancel' => $this->service->cancel($id, $this->tenantID()),
            'run' => $this->service->dispatch($id, $this->tenantID()),
            default => ['status' => 'error', 'code' => 'invalid_campaign_action'],
        };
        return $this->message($response);
    }

    private function listResponse(): array
    {
        $result = $this->model->paginate(max(1, (int)($_REQUEST['page'] ?? 1)), 20, $this->tenantID(), trim((string)($_REQUEST['status'] ?? 'all')));
        return ['status' => 'success', 'code' => 'campaigns_loaded', 'data' => $result['data'], 'meta' => $result['meta']];
    }

    private function withHtml(array $response): array
    {
        $campaigns = (array)($response['data'] ?? []); $meta = (array)($response['meta'] ?? []);
        ob_start(); include ABSPATH . 'app/views/admin/notifications/campaigns/_list.php';
        $response['html'] = (string)ob_get_clean(); return $response;
    }

    private function parseRecipients(string $raw, array $channels): array
    {
        $items = [];
        foreach (preg_split('/\R+/', $raw) ?: [] as $line) {
            $line = trim($line); if ($line === '') continue;
            $channel = count($channels) === 1 ? $channels[0] : '';
            $recipient = $line;
            if (preg_match('/^(inbox|email)\s*:\s*(.+)$/i', $line, $match) === 1) { $channel = strtolower($match[1]); $recipient = trim($match[2]); }
            $items[] = ['channel' => $channel, 'recipient' => $recipient];
        }
        return $items;
    }

    private function tenantID(): ?int { $id = (int)($_SESSION['auth']['tenant_id'] ?? $_SESSION['tenant_id'] ?? 0); return $id > 0 ? $id : null; }
    private function userID(): int { return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0); }

    private function message(array $response): array
    {
        $messages = ['campaign_started' => 'Campaña iniciada.', 'campaign_scheduled' => 'Campaña programada.', 'campaign_paused' => 'Campaña pausada.', 'campaign_resumed' => 'Campaña reanudada.', 'campaign_cancelled' => 'Campaña cancelada.', 'campaign_batch_queued' => 'Lote añadido a la cola.', 'invalid_campaign' => 'Completa el nombre, el contenido y al menos un canal.', 'empty_campaign_audience' => 'Añade al menos un destinatario válido.', 'invalid_campaign_action' => 'Acción no válida.', 'campaign_not_found' => 'Campaña no encontrada.', 'campaign_create_failed' => 'No se pudo crear la campaña.', 'campaign_dispatch_failed' => 'No se pudo procesar la campaña.'];
        $code = (string)($response['code'] ?? ''); if (!isset($response['message']) && isset($messages[$code])) $response['message'] = $messages[$code]; return $response;
    }
}
