<?php

interface CronTaskRepository
{
    public function createTask(array $task): int;
    public function findByKey(string $taskKey): ?array;
    public function updateTask(string $taskKey, array $changes): bool;
    public function reserveDue(int $limit): array;
    public function markSuccess(int $taskID, ?string $nextRunAt = null): void;
    public function markFailed(int $taskID, string $message): void;
    public function recoverStale(int $seconds): int;
}
