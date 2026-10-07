<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class CronRunnerTest extends TestCase
{
    public function testTaskLifecycleUsesStableContracts(): void
    {
        $repository = new InMemoryCronTaskRepository();
        $service = new \CronTaskService($repository);
        $scheduled = $service->schedule('campaigns.dispatch', TestCronHandler::class, '2026-09-28 12:00:00', ['campaign_id' => 7]);

        self::assertSame('success', $scheduled['status']);
        self::assertSame('cron_task_exists', $service->schedule('campaigns.dispatch', TestCronHandler::class, '2026-09-28 12:00:00')['code']);
        self::assertSame('success', $service->pause('campaigns.dispatch')['status']);
        self::assertSame(0, $repository->tasks[1]['is_active']);
        self::assertSame('success', $service->resume('campaigns.dispatch')['status']);
        self::assertSame('success', $service->reschedule('campaigns.dispatch', '2026-09-29 12:00:00')['status']);
        self::assertSame('success', $service->cancel('campaigns.dispatch')['status']);
        self::assertSame('cancelled', $repository->tasks[1]['status']);
    }

    public function testSchedulerReservesAndReschedulesRecurringTasks(): void
    {
        $repository = new InMemoryCronTaskRepository();
        $repository->createTask([
            'task_key' => 'test.recurring', 'handler_class' => TestCronHandler::class,
            'payload_json' => '{"value":3}', 'status' => 'pending',
            'scheduled_at' => gmdate('Y-m-d H:i:s', time() - 10),
            'repeat_interval_seconds' => 3600, 'attempts' => 0, 'is_active' => 1,
        ]);
        TestCronHandler::$handled = [];
        $result = (new \CronScheduler($repository))->runDue(10);

        self::assertSame('success', $result['status']);
        self::assertSame(1, $result['data']['processed']);
        self::assertSame([['value' => 3]], TestCronHandler::$handled);
        self::assertSame('pending', $repository->tasks[1]['status']);
        self::assertNotNull($repository->tasks[1]['scheduled_at']);
    }

    public function testSchedulerConvertsHandlerExceptionsIntoFailedTasks(): void
    {
        $repository = new InMemoryCronTaskRepository();
        $repository->createTask(['task_key' => 'test.failure', 'handler_class' => FailingCronHandler::class, 'payload_json' => '{}', 'status' => 'pending', 'scheduled_at' => gmdate('Y-m-d H:i:s'), 'is_active' => 1]);
        $result = (new \CronScheduler($repository))->runDue();
        self::assertSame(1, $result['data']['failed']);
        self::assertSame('error', $repository->tasks[1]['status']);
    }

    public function testCronCoreDoesNotUseThrowable(): void
    {
        foreach (glob(dirname(__DIR__) . '/src/cron/*.php') ?: [] as $file) {
            self::assertStringNotContainsString('Throwable', (string)file_get_contents($file), $file);
        }
    }

    public function testReturnedErrorsFailWithoutRepeatingAndMissingStatusRemainsCompatible(): void
    {
        foreach (['error', 'failed', null] as $status) {
            ReturnedResultCronHandler::$result = $status === null ? [] : ['status' => $status, 'code' => 'business_failed', 'reschedule' => true];
            $repository = new InMemoryCronTaskRepository();
            $repository->createTask(['task_key' => 'returned', 'handler_class' => ReturnedResultCronHandler::class, 'payload_json' => '{}', 'status' => 'pending', 'scheduled_at' => gmdate('Y-m-d H:i:s'), 'repeat_interval_seconds' => 3600, 'is_active' => 1]);
            $result = (new \CronScheduler($repository))->runDue();
            self::assertSame($status === null ? 0 : 1, $result['data']['failed']);
            self::assertSame($status === null ? 1 : 0, $result['data']['processed']);
            self::assertSame($status === null ? 'pending' : 'error', $repository->tasks[1]['status']);
            if ($status !== null) self::assertSame('business_failed', $repository->tasks[1]['last_error']);
        }
    }
}

final class InMemoryCronTaskRepository implements \CronTaskRepository
{
    public array $tasks = [];
    public function createTask(array $task): int { $id = count($this->tasks) + 1; $this->tasks[$id] = ['task_id' => $id] + $task; return $id; }
    public function findByKey(string $taskKey): ?array { foreach ($this->tasks as $task) if ($task['task_key'] === $taskKey) return $task; return null; }
    public function updateTask(string $taskKey, array $changes): bool { foreach ($this->tasks as &$task) if ($task['task_key'] === $taskKey) { $task = $changes + $task; return true; } return false; }
    public function reserveDue(int $limit): array { $rows = []; foreach ($this->tasks as &$task) if ($task['status'] === 'pending' && !empty($task['is_active']) && strtotime($task['scheduled_at']) <= time()) { $task['status'] = 'processing'; $task['attempts'] = (int)($task['attempts'] ?? 0) + 1; $rows[] = $task; } return array_slice($rows, 0, $limit); }
    public function markSuccess(int $taskID, ?string $nextRunAt = null): void { $this->tasks[$taskID]['status'] = $nextRunAt === null ? 'completed' : 'pending'; if ($nextRunAt !== null) $this->tasks[$taskID]['scheduled_at'] = $nextRunAt; }
    public function markFailed(int $taskID, string $message): void { $this->tasks[$taskID]['status'] = 'error'; $this->tasks[$taskID]['last_error'] = $message; }
    public function recoverStale(int $seconds): int { return 0; }
}

final class TestCronHandler extends \Cron
{
    public static array $handled = [];
    public function handle(array $task = []): array { self::$handled[] = $task; return ['status' => 'success']; }
}

final class FailingCronHandler extends \Cron
{
    public function handle(array $task = []): array { throw new \RuntimeException('Fallo controlado'); }
}

final class ReturnedResultCronHandler extends \Cron
{
    public static array $result = [];
    public function handle(array $task = []): array { return self::$result; }
}
