<?php

final class CronTaskService
{
    public function __construct(private readonly CronTaskRepository $tasks = new CronDataProvider())
    {
    }

    public function schedule(string $taskKey, string $handlerClass, string $scheduledAt, array $payload = [], ?int $repeatIntervalSeconds = null): array
    {
        $taskKey = trim($taskKey);
        $handlerClass = trim($handlerClass);
        if (!$this->validKey($taskKey) || $handlerClass === '' || !$this->validDate($scheduledAt)) {
            return ['status' => 'error', 'code' => 'invalid_cron_task'];
        }
        if ($repeatIntervalSeconds !== null && $repeatIntervalSeconds < 60) {
            return ['status' => 'error', 'code' => 'invalid_cron_interval'];
        }
        try {
            if ($this->tasks->findByKey($taskKey) !== null) {
                return ['status' => 'error', 'code' => 'cron_task_exists'];
            }
            $id = $this->tasks->createTask([
                'task_key' => $taskKey,
                'handler_class' => $handlerClass,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
                'repeat_interval_seconds' => $repeatIntervalSeconds,
                'attempts' => 0,
                'is_active' => 1,
            ]);
            return ['status' => 'success', 'code' => 'cron_task_scheduled', 'data' => ['task_id' => $id, 'task_key' => $taskKey]];
        } catch (Exception $exception) {
            error_log('[GFrame Cron] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'cron_task_schedule_failed'];
        }
    }

    public function reschedule(string $taskKey, string $scheduledAt): array
    {
        if (!$this->validKey($taskKey) || !$this->validDate($scheduledAt)) return ['status' => 'error', 'code' => 'invalid_cron_task'];
        return $this->change($taskKey, ['scheduled_at' => $scheduledAt, 'status' => 'pending', 'is_active' => 1, 'locked_at' => null, 'last_error' => null], 'cron_task_rescheduled');
    }

    public function pause(string $taskKey): array
    {
        return $this->change($taskKey, ['is_active' => 0], 'cron_task_paused');
    }

    public function resume(string $taskKey): array
    {
        return $this->change($taskKey, ['is_active' => 1, 'status' => 'pending'], 'cron_task_resumed');
    }

    public function cancel(string $taskKey): array
    {
        return $this->change($taskKey, ['is_active' => 0, 'status' => 'cancelled', 'locked_at' => null], 'cron_task_cancelled');
    }

    private function change(string $taskKey, array $changes, string $code): array
    {
        if (!$this->validKey($taskKey)) return ['status' => 'error', 'code' => 'invalid_cron_task'];
        try {
            if (!$this->tasks->updateTask($taskKey, $changes)) return ['status' => 'error', 'code' => 'cron_task_not_found'];
            return ['status' => 'success', 'code' => $code, 'data' => ['task_key' => $taskKey]];
        } catch (Exception $exception) {
            error_log('[GFrame Cron] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'cron_task_update_failed'];
        }
    }

    private function validKey(string $key): bool { return preg_match('/^[a-z0-9][a-z0-9_.:-]{0,119}$/i', $key) === 1; }
    private function validDate(string $date): bool { return trim($date) !== '' && strtotime($date) !== false; }
}
