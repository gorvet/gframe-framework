<?php

namespace GFrame\Modules\NotificationCampaigns\Controllers;

use CronTaskService;
use GFrame\Notifications\Campaigns\AutomaticCampaignModel;

use GFrame\Notifications\Campaigns\CampaignModel;
use GFrame\Notifications\Campaigns\CampaignService;
use GFrame\Notifications\Campaigns\CampaignUserAudience;
use GFrame\Notifications\NotificationQueueModel;

class CampaignController
{
    protected CampaignModel $model;
    protected CampaignService $service;
    protected AutomaticCampaignModel $automaticModel;
    protected CampaignUserAudience $audience;

    public function __construct(?CampaignModel $model = null, ?CampaignService $service = null, ?AutomaticCampaignModel $automaticModel = null, ?CampaignUserAudience $audience = null)
    {
        $this->audience = $audience ?? new CampaignUserAudience();
        $this->automaticModel = $automaticModel ?? new AutomaticCampaignModel();
        $this->model = $model ?? new CampaignModel();
        $this->service = $service ?? new CampaignService($this->model, new NotificationQueueModel(), new CronTaskService(), $this->audience);
    }

    public function index(): array { return $this->withHtml($this->listResponse()); }
    public function list(): array { return $this->withHtml($this->listResponse()); }

    public function automatic(): array
    {
        try {
            $model = $this->automaticModel;
            $rules = $model->rules($this->tenantID() ?? 0);
            return ['status' => 'success', 'code' => 'automatic_campaigns_loaded', 'data' => ['rules' => $rules]];
        } catch (\Throwable $exception) {
            error_log('[GFrame Campaigns] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'automatic_campaigns_failed', 'message' => 'No se pudieron cargar las reglas automáticas.'];
        }
    }

    public function saveAutomatic(): array
    {
        $key = (string)($_POST['rule_key'] ?? '');
        $title = trim((string)($_POST['title'] ?? '')); $message = trim((string)($_POST['message'] ?? ''));
        $days = filter_var($_POST['cooldown_days'] ?? 7, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
        if ($days === false) return ['status' => 'error', 'code' => 'invalid_campaign_cooldown', 'message' => 'Indica entre 1 y 3650 días sin repetir el aviso.'];
        if ($this->automaticModel->definition($key) === null || $title === '' || $message === '' || mb_strlen($title) > 160 || mb_strlen($message) > 10000) return ['status' => 'error', 'code' => 'invalid_campaign_rule', 'message' => 'Revisa el título y el mensaje de la regla.'];
        try {
            ($this->automaticModel)->saveRule($this->tenantID() ?? 0, $key, !empty($_POST['is_active']), $title, $message, $days);
            $cron = new CronTaskService();
            $taskKey = 'automatic-campaigns.' . ($this->tenantID() ?? 0);
            $task = $cron->schedule($taskKey, $this->automaticCronHandler(), gmdate('Y-m-d H:i:s'), ['tenant_id' => $this->tenantID(), 'site_url' => (string)site_url], 3600);
            if (($task['code'] ?? '') === 'cron_task_exists') {
                $updated = (new \CronDataProvider())->updateTask($taskKey, [
                    'handler_class' => $this->automaticCronHandler(),
                    'payload_json' => json_encode(['tenant_id' => $this->tenantID(), 'site_url' => (string)site_url]),
                ]);
                $task = $updated ? $cron->resume($taskKey) : ['status' => 'error', 'code' => 'cron_task_update_failed'];
            }
            if (($task['status'] ?? '') !== 'success') return ['status' => 'error', 'code' => 'automatic_campaign_schedule_failed', 'message' => 'Regla guardada, pero no se pudo programar su revisión periódica.'];
            return ['status' => 'success', 'code' => 'campaign_rule_saved', 'message' => 'Regla guardada.', 'data' => ['state_label' => !empty($_POST['is_active']) ? 'Activa' : 'Inactiva', 'cooldown_days' => $days]];
        } catch (\Throwable $exception) {
            error_log('[GFrame Campaigns] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'campaign_rule_save_failed', 'message' => 'No se pudo guardar la regla.'];
        }
    }

    public function sendAutomatic(): array
    {
        $key = (string)($_POST['rule_key'] ?? '');
        if ($this->automaticModel->definition($key) === null || ($key === 'account_deletion_reminder' && $this->tenantID() !== null)) return ['status' => 'error', 'code' => 'campaign_manual_context_required', 'message' => 'Esta regla no está disponible en este ámbito.'];
        $users = ($this->automaticModel)->eligibleUsers($key, $this->tenantID() ?? 0);
        if ($key === 'account_deletion_reminder') {
            $recorded = array_column((new \GFrame\Notifications\Campaigns\AccountDeactivationModel())->candidates(), 'user_id');
            $users = array_filter($users, static fn(array $user): bool => in_array($user['user_id'], $recorded));
        }
        $ids = array_map(static fn(array $user): int => (int)$user['user_id'], $users);
        $eventID = bin2hex(random_bytes(16));
        $counts = ['queued' => 0, 'suppressed' => 0, 'failed' => 0];
        foreach ($ids as $id) {
            $result = $key === 'account_deletion_reminder'
                ? (new \GFrame\Notifications\Campaigns\AccountDeactivationLifecycle())->remind($id, (string)site_url, true, $eventID)
                : \GFrame\Notifications\Campaigns\AutomaticCampaignDispatcher::emit($key, $id, $eventID, ['site_url' => (string)site_url], $this->tenantID(), true, $this->automaticModel);
            $counts[($result['code'] ?? '') === 'automatic_campaign_queued' ? 'queued' : (($result['code'] ?? '') === 'automatic_campaign_suppressed' ? 'suppressed' : 'failed')]++;
        }
        return ['status' => $ids !== [] && $counts['failed'] === count($ids) ? 'error' : 'success', 'code' => 'campaign_manual_processed', 'message' => $ids === [] ? 'No hay destinatarios elegibles para esta regla.' : sprintf('Añadidos a la cola: %d. Omitidos por aviso reciente: %d. No procesados: %d.', $counts['queued'], $counts['suppressed'], $counts['failed']), 'data' => $counts];
    }

    public function automaticHistory(): array
    {
        try {
            $response = (new \GFrame\Notifications\Campaigns\AutomaticCampaignHistoryModel())->listHistory($this->tenantID() ?? 0, max(1, (int)($_REQUEST['page'] ?? 1)));
            $items = $response['data']; $meta = $response['meta'];
            $automaticModel = $this->automaticModel;
            ob_start(); include $this->viewPath('_automaticHistory.php');
            return ['status' => 'success', 'code' => 'automatic_campaign_history_loaded', 'data' => $items, 'meta' => $meta, 'html' => (string)ob_get_clean()];
        } catch (\Exception $exception) {
            error_log('[GFrame Campaign History] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'automatic_campaign_history_failed', 'message' => 'No se pudo cargar el historial.'];
        }
    }

    public function new(): array
    {
        $campaign = [];
        $sourceID = (int)($_GET['source'] ?? 0);
        if ($sourceID > 0) {
            $source = $this->model->findCampaign($sourceID, $this->tenantID());
            if ($source === null) return $this->message(['status' => 'error', 'code' => 'campaign_not_found']);
            $campaign = array_intersect_key($source, array_flip(['title', 'message', 'channels_json', 'template_id', 'importance', 'action_url', 'expires_after_days']));
        }
        return ['status' => 'success', 'code' => 'campaign_form_loaded', 'data' => ['campaign' => $campaign, 'users' => ($this->audience)->users($this->tenantID())]];
    }

    public function edit(): array
    {
        $campaign = $this->model->findCampaign((int)($_GET['id'] ?? 0), $this->tenantID());
        if ($campaign === null) return $this->message(['status' => 'error', 'code' => 'campaign_not_found']);
        $progress = $this->model->refreshProgress((int)$campaign['campaign_id']);
        $recurring = ($campaign['recurrence'] ?? 'once') !== 'once';
        if ((!$recurring && (!in_array($campaign['status'], ['draft', 'scheduled', 'paused'], true) || $progress['pending'] !== $progress['total'])) || $campaign['status'] === 'cancelled') return $this->message(['status' => 'error', 'code' => 'campaign_not_editable']);
        $criteria = $this->model->audienceFor($campaign);
        if ($recurring && $progress['pending'] !== $progress['total']) {
            $plan = (new \GFrame\Notifications\Campaigns\CampaignRecurrenceModel())->plan((int)$campaign['campaign_id']);
            $campaign['scheduled_at'] = $plan['next_at'] ?? null;
        }
        return ['status' => 'success', 'code' => 'campaign_form_loaded', 'data' => ['campaign' => $campaign, 'criteria' => $criteria, 'users' => ($this->audience)->users($this->tenantID())]];
    }

    public function update(): array
    {
        $fields = [];
        foreach (['title' => 160, 'message' => 10000] as $field => $limit) {
            $value = trim((string)($_POST[$field] ?? ''));
            if ($value === '' || mb_strlen($value, 'UTF-8') > $limit) return $this->message(['status' => 'error', 'code' => 'invalid_campaign']);
            $fields[$field] = $value;
        }
        $fields['name'] = $fields['title'];
        $options = $this->campaignOptions();
        if ($options === null) return ['status' => 'error', 'code' => 'invalid_campaign_options', 'message' => 'Revisa el enlace, la importancia y la caducidad.'];
        $fields += $options;
        $scope = (string)($_POST['audience'] ?? 'active');
        $channels = array_values(array_intersect(['inbox', 'email'], (array)($_POST['channels'] ?? [])));
        $recurrence = (string)($_POST['recurrence'] ?? 'once');
        if (!in_array($scope, ['active', 'administrators', 'manual'], true) || !in_array($recurrence, ['once', 'daily', 'weekly'], true) || $channels === []) return $this->message(['status' => 'error', 'code' => 'invalid_campaign']);
        try { $fields['scheduled_at'] = \GFrame\Notifications\Campaigns\CampaignSchedule::utc((string)($_POST['scheduled_at'] ?? ''), (string)($_POST['user_timezone'] ?? 'UTC')); }
        catch (\Exception $exception) { return ['status' => 'error', 'code' => 'invalid_campaign_schedule', 'message' => 'Revisa la fecha y la zona horaria.']; }
        $fields['recurrence'] = $recurrence; $fields['channels_json'] = json_encode($channels);
        $criteria = ['scope' => $scope, 'channels' => $channels, 'user_ids' => $scope === 'manual' ? array_map('intval', (array)($_POST['user_ids'] ?? [])) : [], 'tenant_id' => $this->tenantID(), 'site_url' => (string)site_url];
        $id = (int)($_POST['campaign_id'] ?? 0);
        $response = $this->model->updateDefinition($id, $fields, $criteria, ($this->audience)->recipients($criteria), $this->tenantID());
        if (!empty($response['data']['dispatch_now'])) {
            $response['data']['dispatch'] = $this->service->dispatch($id, $this->tenantID());
            $response = $this->startEmailDelivery($response, in_array('email', $channels, true));
        }
        return $this->message($response);
    }

    public function create(): array
    {
        $channels = array_values(array_intersect(['inbox', 'email'], array_map('strval', (array)($_POST['channels'] ?? []))));
        $scope = (string)($_POST['audience'] ?? 'active');
        if (!in_array($scope, ['active', 'administrators', 'manual'], true)) return ['status' => 'error', 'code' => 'invalid_campaign_audience', 'message' => 'Selecciona una audiencia válida.'];
        $options = $this->campaignOptions();
        $recurrence = (string)($_POST['recurrence'] ?? 'once');
        if ($options === null || !in_array($recurrence, ['once', 'daily', 'weekly'], true)) return ['status' => 'error', 'code' => 'invalid_campaign_options', 'message' => 'Revisa las opciones de la campaña.'];
        try { $scheduledAt = \GFrame\Notifications\Campaigns\CampaignSchedule::utc((string)($_POST['scheduled_at'] ?? ''), (string)($_POST['user_timezone'] ?? 'UTC')); }
        catch (\Exception $exception) { return ['status' => 'error', 'code' => 'invalid_campaign_schedule', 'message' => 'Revisa la fecha y la zona horaria.']; }
        $input = [
            'name' => (string)($_POST['title'] ?? ''), 'title' => (string)($_POST['title'] ?? ''),
            'message' => (string)($_POST['message'] ?? ''), 'template_id' => (string)($_POST['template_id'] ?? 'notification'),
            'channels' => $channels, 'scheduled_at' => $scheduledAt ?? '', 'recurrence' => $recurrence,
            'audience' => ['scope' => $scope, 'tenant_id' => $this->tenantID(), 'user_ids' => $scope === 'manual' ? array_map('intval', (array)($_POST['user_ids'] ?? [])) : [], 'channels' => $channels, 'site_url' => (string)site_url],
        ] + $options;
        $response = $this->service->create($input, $this->audience, $this->tenantID(), $this->userID());
        if (($response['status'] ?? '') === 'success' && $recurrence !== 'once') {
            try {
                $registration = (new \GFrame\Notifications\Campaigns\CampaignRecurrenceModel())->register((int)$response['data']['campaign_id'], $input['audience'], \GFrame\Notifications\Campaigns\CampaignSchedule::next($recurrence, $scheduledAt ?? gmdate('Y-m-d H:i:s')));
                if (($registration['status'] ?? '') !== 'success') throw new \RuntimeException('Recurrence registration failed');
            } catch (\Exception $exception) {
                error_log('[GFrame Campaign Recurrence] ' . $exception->getMessage());
                $response['message'] = 'Campaña creada, pero no se pudo programar su repetición.';
                $response['code'] = 'campaign_recurrence_registration_failed';
                $response['status'] = 'error';
            }
        }
        $response = $this->startEmailDelivery($response, $scheduledAt === null && in_array('email', $channels, true));
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
        return $this->message($this->startEmailDelivery($response, $action === 'run'));
    }

    protected function campaignOptions(): ?array
    {
        $importance = (string)($_POST['importance'] ?? 'info');
        $url = trim((string)($_POST['action_url'] ?? ''));
        $days = filter_var($_POST['expires_after_days'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3650]]);
        if (!in_array($importance, ['info', 'warning', 'danger'], true) || $days === false || mb_strlen($url) > 255 || !\GFrame\Notifications\Campaigns\CampaignPlaceholders::validActionURL($url)) return null;
        return ['importance' => $importance, 'action_url' => $url, 'expires_after_days' => $days];
    }

    public function previewAudience(): array
    {
        $scope = (string)($_POST['audience'] ?? 'active');
        if (!in_array($scope, ['active', 'administrators', 'manual'], true)) return ['status' => 'error', 'code' => 'invalid_campaign_audience', 'message' => 'Selecciona una audiencia válida.'];
        $users = ($this->audience)->users($this->tenantID(), $scope === 'manual' ? array_map('intval', (array)($_POST['user_ids'] ?? [])) : [], $scope);
        $total = count($users); $sample = array_slice($users, 0, 10);
        ob_start(); include $this->viewPath('_audiencePreview.php');
        return ['status' => 'success', 'code' => 'campaign_audience_previewed', 'data' => ['total' => $total], 'html' => (string)ob_get_clean()];
    }

    public function testSend(): array
    {
        $title = trim((string)($_POST['title'] ?? '')); $message = trim((string)($_POST['message'] ?? ''));
        $options = $this->campaignOptions();
        if ($title === '' || $message === '' || mb_strlen($title) > 160 || mb_strlen($message) > 10000 || $options === null) return ['status' => 'error', 'code' => 'invalid_campaign', 'message' => 'Completa el título y el mensaje.'];
        $channels = array_values(array_intersect(['inbox', 'email'], (array)($_POST['channels'] ?? [])));
        $criteria = ['scope' => 'manual', 'user_ids' => [$this->userID()], 'channels' => $channels, 'tenant_id' => $this->tenantID(), 'site_url' => (string)site_url];
        $count = 0;
        try {
            foreach (($this->audience)->recipients($criteria) as $user) {
                $variables = $user['variables'];
                $resolvedTitle = '[Prueba] ' . \GFrame\Notifications\Campaigns\CampaignPlaceholders::render($title, $variables);
                $resolvedMessage = \GFrame\Notifications\Campaigns\CampaignPlaceholders::render($message, $variables);
                $actionURL = \GFrame\Notifications\Campaigns\CampaignPlaceholders::actionURL($options['action_url'], $variables);
                foreach ($user['recipients'] as $channel => $recipient) {
                    (new NotificationQueueModel())->enqueue(['tenant_id' => $this->tenantID(), 'channel' => $channel, 'recipient' => $recipient, 'payload' => [
                        'title' => $resolvedTitle, 'subject' => $resolvedTitle, 'message' => $resolvedMessage,
                        'importance' => $options['importance'], 'action_url' => $actionURL,
                        'expires_at' => $options['expires_after_days'] > 0 ? date('Y-m-d H:i:s', time() + $options['expires_after_days'] * 86400) : null,
                        'template' => 'notification', 'variables' => ['title' => $resolvedTitle, 'message' => $resolvedMessage, 'action_url' => $actionURL] + $variables,
                    ]]);
                    $count++;
                }
            }
        } catch (\Exception $exception) {
            error_log('[GFrame Campaign Test] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'campaign_test_failed', 'message' => 'No se pudo completar el envío de prueba.', 'data' => ['queued' => $count]];
        }
        return $this->startEmailDelivery(['status' => $count > 0 ? 'success' : 'error', 'code' => 'campaign_test_queued', 'message' => $count > 0 ? 'Prueba añadida a la cola únicamente para tu cuenta.' : 'Selecciona un canal disponible para tu cuenta.', 'data' => ['queued' => $count]], in_array('email', $channels, true));
    }

    protected function startEmailDelivery(array $response, bool $requested): array
    {
        if (!$requested || ($response['status'] ?? '') !== 'success') return $response;
        if (!\GFrame\Modules\ModuleRuntime::isInstalled('notifications-email')) return $response;
        try {
            $this->dispatchEmailWorker();
            $response['meta']['email_worker'] = 'started';
        } catch (\Exception $exception) {
            error_log('[GFrame Campaign Email Worker] ' . $exception->getMessage());
            $response['meta']['email_worker'] = 'failed';
            $response['message'] = 'El envío quedó en cola, pero no se pudo iniciar su procesamiento. Se reintentará mediante cron.';
        }
        return $response;
    }

    protected function dispatchEmailWorker(): void
    {
        (new \GFrame\Notifications\NotificationQueueWorker())->dispatchAsync(\GFrame\Notifications\Email\EmailQueueProcessor::class);
    }

    protected function listResponse(): array
    {
        $result = $this->model->paginateCampaigns(max(1, (int)($_REQUEST['page'] ?? 1)), 20, $this->tenantID(), trim((string)($_REQUEST['status'] ?? 'all')));
        $access = (new \GFrame\Auth\RolePermissionService(new \GFrame\Auth\RoleModel()))->authorize($this->userID(), 'notifications.campaigns.manage', $this->tenantID() ?? 0);
        return ['status' => 'success', 'code' => 'campaigns_loaded', 'data' => $result['data'], 'meta' => $result['meta'], 'can_manage' => ($access['status'] ?? '') === 'success'];
    }

    protected function withHtml(array $response): array
    {
        $campaigns = (array)($response['data'] ?? []); $meta = (array)($response['meta'] ?? []);
        $canManage = !empty($response['can_manage']);
        ob_start(); include $this->viewPath('_list.php');
        $response['html'] = (string)ob_get_clean(); return $response;
    }

    protected function viewPath(string $name): string
    {
        $path = \GFrame\Modules\ModuleRuntime::file('views', 'notification-campaigns/' . $name, 'notification-campaigns');
        if ($path === null) throw new \RuntimeException('No se encontró la vista de Campañas.');
        return $path;
    }

    protected function automaticCronHandler(): string
    {
        return \GFrame\Notifications\Campaigns\AutomaticCampaignCronHandler::class;
    }

    protected function tenantID(): ?int { $id = (int)($_SESSION['auth']['tenant_id'] ?? $_SESSION['tenant_id'] ?? 0); return $id > 0 ? $id : null; }
    protected function userID(): int { return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0); }

    protected function message(array $response): array
    {
        $extra = ['campaign_updated' => 'Campaña guardada.', 'campaign_update_failed' => 'No se pudo guardar la campaña.', 'campaign_not_editable' => 'La campaña ya ha comenzado a procesarse. Recíclala para crear un nuevo envío.'];
        if (isset($extra[$response['code'] ?? ''])) $response['message'] = $extra[$response['code']];
        $messages = ['campaign_started' => 'Campaña iniciada.', 'campaign_scheduled' => 'Campaña programada.', 'campaign_paused' => 'Campaña pausada.', 'campaign_resumed' => 'Campaña reanudada.', 'campaign_cancelled' => 'Campaña cancelada.', 'campaign_batch_queued' => 'Lote añadido a la cola.', 'invalid_campaign' => 'Completa el nombre, el contenido y al menos un canal.', 'empty_campaign_audience' => 'Añade al menos un destinatario válido.', 'invalid_campaign_action' => 'Acción no válida.', 'campaign_not_found' => 'Campaña no encontrada.', 'campaign_create_failed' => 'No se pudo crear la campaña.', 'campaign_dispatch_failed' => 'No se pudo procesar la campaña.'];
        $code = (string)($response['code'] ?? ''); if (!isset($response['message']) && isset($messages[$code])) $response['message'] = $messages[$code]; return $response;
    }
}
