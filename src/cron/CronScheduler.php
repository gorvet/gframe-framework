<?php

final class CronScheduler extends CronRunner
{
    public function __construct(
        private readonly CronTaskRepository $tasks = new CronDataProvider(),
        private readonly ?\GFrame\Cron\CronRuntime $runtime = null
    )
    {
    }

    public function runDue(int $limit = 10, ?string $launchToken = null, ?Closure $prepare = null): array
    {
        return $this->runCron('gframe.scheduler', function () use ($limit, $launchToken, $prepare): array {
            $runtime = $this->runtime ?? \GFrame\Cron\CronRuntime::frameworkDefault();
            if (!$runtime->acquire()) return ['processed' => 0, 'failed' => 0, 'recovered' => 0, 'busy' => true];
            try {
                if (!$runtime->start($launchToken)) return ['processed' => 0, 'failed' => 0, 'recovered' => 0, 'skipped' => true];
                try {
                    if ($prepare !== null) $prepare();
                    $data = $this->processDue(max(1, min(100, $limit)), $runtime);
                    $runtime->finish(['status' => 'success', 'data' => $data]);
                    return $data;
                } catch (Throwable $exception) {
                    $runtime->finish(['status' => 'error', 'message' => $exception->getMessage()]);
                    throw $exception;
                }
            } finally {
                $runtime->release();
            }
        });
    }

    private function processDue(int $limit, \GFrame\Cron\CronRuntime $runtime): array
    {
        $runtime->assertOwned();
        $processed = 0;
        $failed = 0;
        // El bloqueo de proceso prueba que no hay otro runner vivo propietario.
        $recovered = $this->tasks->recoverStale(0);

        foreach ($this->tasks->reserveDue($limit) as $task) {
            $taskID = (int)($task['task_id'] ?? 0);
            $handlerClass = trim((string)($task['handler_class'] ?? ''));
            if ($taskID <= 0) continue;

            try {
                $runtime->assertOwned();
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
                $nextRunAt = null;
                if ($shouldRepeat && $interval >= 60) {
                    $now = time();
                    $scheduledAt = date_create_immutable((string)$task['scheduled_at'], new DateTimeZone('UTC'));
                    $nextRunAt = gmdate('Y-m-d H:i:s', max($now, $scheduledAt === false ? $now : $scheduledAt->getTimestamp()) + $interval);
                }
                $runtime->assertOwned();
                $this->tasks->markSuccess($taskID, $nextRunAt);
                $processed++;
            } catch (Throwable $exception) {
                // Frontera de ejecución del handler; también registra errores PHP.
                $runtime->assertOwned();
                $this->tasks->markFailed($taskID, $exception->getMessage());
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed, 'recovered' => $recovered];
    }
}
