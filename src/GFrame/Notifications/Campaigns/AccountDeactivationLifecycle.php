<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Auth\UserModel;
use GFrame\Notifications\NotificationQueueModel;

final class AccountDeactivationLifecycle
{
    private int $days;
    private int $warningHours;

    public function __construct(?int $days = null, ?int $warningHours = null)
    {
        $this->days = $days ?? (function_exists('config') ? (int)\config('auth.deactivation.retention_days', 60) : 60);
        $this->warningHours = $warningHours ?? (function_exists('config') ? (int)\config('auth.deactivation.warning_hours', 72) : 72);
        if ($this->days < 1 || $this->days > 3650 || $this->warningHours < 1 || $this->warningHours >= $this->days * 24) throw new \InvalidArgumentException('Invalid account deactivation policy');
    }

    public function policy(): array { return ['retention_days' => $this->days, 'warning_hours' => $this->warningHours]; }

    public function register(int $userID, string $siteURL, bool $newCycle = false): array
    {
        $transaction = false;
        try {
            $user = (new UserModel())->findAccountByID($userID);
            if (!$user || $user['status'] !== 'disabled' || !(new UserModel())->canDeactivateAccount($user)) return ['status' => 'error', 'code' => 'account_deactivation_ineligible'];
            $model = new AccountDeactivationModel();
            if (!$newCycle && $model->pending($userID)) return $this->schedule($siteURL);
            \ORM::beginTransaction(); $transaction = true;
            $date = gmdate('Y-m-d H:i:s', time() + $this->days * 86400);
            $deactivatedAt = gmdate('Y-m-d H:i:s');
            $model->record($userID, $deactivatedAt, $date);
            $title = 'Tu cuenta ha sido desactivada';
            $name = trim((string)($user['name'] ?? '')) ?: (string)strtok($user['email'], '@');
            $message = 'Hola, ' . $name . ".\n\nLa eliminación de tu cuenta está prevista para el " . $date . ' UTC. Recibirás un aviso al menos ' . $this->warningHours . ' horas antes de que se elimine.';
            $id = (new NotificationQueueModel())->enqueue(['channel' => 'email', 'recipient' => $user['email'], 'deduplication_key' => 'account-deactivation:' . hash('sha256', $userID . ':' . $deactivatedAt), 'payload' => ['subject' => $title, 'title' => $title, 'message' => $message, 'template' => 'notification', 'variables' => ['title' => $title, 'message' => $message]]]);
            $model->change($userID, ['confirmation_id' => $id]);
            (new AutomaticCampaignHistoryModel())->record(0, 'account_deactivated', $deactivatedAt . ':' . $userID, 'automatic', $title, $userID, $id);
            \ORM::commit(); $transaction = false;
            $task = $this->schedule($siteURL);
            if (($task['status'] ?? '') !== 'success') return $task;
            return ['status' => 'success', 'code' => 'account_deactivation_registered', 'data' => ['delete_at' => $date, 'confirmation_id' => $id]];
        } catch (\Exception $exception) {
            if ($transaction) \ORM::rollBack();
            error_log('[GFrame Account Lifecycle] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'account_deactivation_registration_failed'];
        }
    }

    private function schedule(string $siteURL): array
    {
        $cron = new \CronTaskService();
        $task = $cron->schedule('automatic-campaigns.0', AutomaticCampaignCronHandler::class, gmdate('Y-m-d H:i:s'), ['tenant_id' => null, 'site_url' => $siteURL], 3600);
        if (($task['code'] ?? '') === 'cron_task_exists') $task = $cron->resume('automatic-campaigns.0');
        return ['status' => ($task['status'] ?? '') === 'success' ? 'success' : 'error', 'code' => ($task['status'] ?? '') === 'success' ? 'account_deactivation_registered' : 'account_deactivation_schedule_failed'];
    }

    public function remind(int $userID, string $siteURL, bool $manual = false, ?string $historyRunID = null): array
    {
        $model = new AccountDeactivationModel();
        $record = $model->pending($userID);
        if (!$record) return ['status' => 'error', 'code' => 'account_deactivation_not_recorded'];
        // A late runner postpones the date instead of sending an already-expired warning.
        $date = max(strtotime($record['delete_at'] . ' UTC'), time() + $this->warningHours * 3600);
        $result = AutomaticCampaignDispatcher::emit('account_deletion_reminder', $userID, 'deletion:' . $record['deactivated_at'] . ':' . gmdate('Y-m-d'), ['site_url' => $siteURL, 'deletion_policy' => 'self-account-deactivation', 'deletion_date' => gmdate('Y-m-d H:i:s', $date), 'history_run_id' => $historyRunID], null, $manual);
        if (($result['code'] ?? '') === 'automatic_campaign_queued') {
            $changes = ['reminder_id' => (int)$result['data']['notification_id'], 'delete_at' => gmdate('Y-m-d H:i:s', $date)];
            if (!$manual) $changes['warning_id'] = $changes['reminder_id'];
            $model->change($userID, $changes);
        } elseif (!$manual && ($result['code'] ?? '') === 'automatic_campaign_suppressed' && !empty($record['reminder_id'])) {
            $model->change($userID, ['warning_id' => (int)$record['reminder_id']]);
        }
        return $result;
    }

    public function process(string $siteURL): array
    {
        $counts = ['queued' => 0, 'deleted' => 0, 'cancelled' => 0, 'failed' => 0];
        $model = new AccountDeactivationModel();
        foreach ($model->candidates() as $record) {
            $id = (int)$record['user_id'];
            try {
                $user = (new UserModel())->findAccountByID($id);
                if (!$user || $user['status'] !== 'disabled' || !(new UserModel())->canDeactivateAccount($user)) { $model->change($id, ['status' => 'cancelled']); $counts['cancelled']++; continue; }
                if (strtotime($record['delete_at'] . ' UTC') > time() + $this->warningHours * 3600) continue;
                if (empty($record['warning_id'])) {
                    $result = $this->remind($id, $siteURL);
                    if (($result['code'] ?? '') === 'automatic_campaign_queued') $counts['queued']++;
                    elseif (($result['status'] ?? '') === 'error') $counts['failed']++;
                }
                if ($model->deleteDueAccount($id, $this->warningHours)) $counts['deleted']++;
            } catch (\Exception $exception) { error_log('[GFrame Account Lifecycle] ' . $exception->getMessage()); $counts['failed']++; }
        }
        return ['status' => 'success', 'code' => 'account_deactivations_processed', 'data' => $counts];
    }
}
