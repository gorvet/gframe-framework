<?php

final class CronScheduler extends CronRunner
{
    public function __construct(private readonly CronTaskRepository $tasks = new CronDataProvider())
    {
    }

    public function runDue(int $limit = 10): array
    {
        return $this->runCron('gframe.scheduler', function () use ($limit): array {
            $processed = 0;
            $failed = 0;
            $recovered = $this->tasks->recoverStale(900);

            foreach ($this->tasks->reserveDue($limit) as $task) {
                $taskID = (int)($task['task_id'] ?? 0);
                $handlerClass = trim((string)($task['handler_class'] ?? ''));
                if ($taskID <= 0) continue;

                try {
                    if ($handlerClass === '' || !class_exists($handlerClass)) {
                        throw new RuntimeException('No se encontró el manejador de la tarea programada.');
                    }
                    $handler = new $handlerClass();
                    if (!$handler instanceof Cron) {
                        throw new RuntimeException('El manejador debe extender Cron.');
                    }
                    $payload = json_decode((string)($task['payload_json'] ?? '{}'), true);
                    $handlerResult = $handler->handle(is_array($payload) ? $payload : []);
                    if (in_array($handlerResult['status'] ?? null, ['error', 'failed'], true)) {
                        throw new RuntimeException((string)($handlerResult['message'] ?? $handlerResult['code'] ?? 'La tarea devolvió un resultado de error.'));
                    }
                    $interval = (int)($task['repeat_interval_seconds'] ?? 0);
                    $shouldRepeat = !array_key_exists('reschedule', $handlerResult) || !empty($handlerResult['reschedule']);
                    $nextRunAt = $shouldRepeat && $interval >= 60 ? gmdate('Y-m-d H:i:s', max(time(), strtotime((string)$task['scheduled_at']) ?: time()) + $interval) : null;
                    $this->tasks->markSuccess($taskID, $nextRunAt);
                    $processed++;
                } catch (Exception $exception) {
                    $this->tasks->markFailed($taskID, $exception->getMessage());
                    $failed++;
                }
            }

            return ['processed' => $processed, 'failed' => $failed, 'recovered' => $recovered];
        });
    }
}
