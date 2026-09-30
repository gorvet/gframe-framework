<?php

class CronDataProvider extends ORM implements CronTaskRepository {
  protected $table = 'cron_tasks';
  protected $primaryKey = 'task_id';
  protected $fillable = [
    'task_id',
    'task_key',
    'handler_class',
    'payload_json',
    'status',
    'scheduled_at',
    'locked_at',
    'last_run_at',
    'last_error',
    'repeat_interval_seconds',
    'attempts',
    'is_active',
  ];

  public function createTask(array $task): int {
    return (int)(new static($task))->insert();
  }

  public function findByKey(string $taskKey): ?array {
    $rows = $this->reset()->where('task_key', '=', $taskKey)->limit(1)->get();
    return isset($rows[0]) ? (array)$rows[0] : null;
  }

  public function updateTask(string $taskKey, array $changes): bool {
    $result = $this->reset()->where('task_key', '=', $taskKey)->update($changes);
    return (int)($result['matched'] ?? 0) > 0;
  }

  public function getDueTasks(int $limit = 10): array {
    $limit = max(1, min(100, $limit));
    $now = gmdate('Y-m-d H:i:s');

    return $this->reset()
      ->queryTable($this->table)
      ->where('is_active', '=', 1)
      ->where('status', '=', 'pending')
      ->where('scheduled_at', '<=', $now)
      ->orderBy('scheduled_at', 'ASC')
      ->orderBy('task_id', 'ASC')
      ->limit($limit)
      ->get();
  }

  public function reserveDue(int $limit): array {
    $reserved = [];
    foreach ($this->getDueTasks($limit) as $task) {
      $taskID = (int)($task['task_id'] ?? 0);
      if ($taskID <= 0) continue;
      $claimed = $this->reset()->where('task_id', '=', $taskID)->where('status', '=', 'pending')->where('is_active', '=', 1)->update([
        'status' => 'processing', 'locked_at' => gmdate('Y-m-d H:i:s'), 'attempts' => (int)($task['attempts'] ?? 0) + 1,
      ]);
      if ((int)($claimed['affected'] ?? 0) < 1) continue;
      $task['attempts'] = (int)($task['attempts'] ?? 0) + 1;
      $reserved[] = $task;
    }
    return $reserved;
  }

  public function markProcessing(int $taskID): array {
    return $this->reset()
      ->queryTable($this->table)
      ->where('task_id', '=', $taskID)
      ->update([
        'status' => 'processing',
        'locked_at' => gmdate('Y-m-d H:i:s'),
      ]);
  }

  public function markSuccess(int $taskID, ?string $nextRunAt = null): void {
    $changes = [
      'status' => $nextRunAt === null ? 'completed' : 'pending',
      'locked_at' => null,
      'last_error' => null,
      'last_run_at' => gmdate('Y-m-d H:i:s'),
    ];
    if ($nextRunAt !== null) $changes['scheduled_at'] = $nextRunAt;
    $this->reset()
      ->queryTable($this->table)
      ->where('task_id', '=', $taskID)
      ->update($changes);
  }

  public function markFailed(int $taskID, string $message): void {
    $this->reset()
      ->queryTable($this->table)
      ->where('task_id', '=', $taskID)
      ->update([
        'status' => 'error',
        'locked_at' => null,
        'last_error' => trim($message),
        'last_run_at' => gmdate('Y-m-d H:i:s'),
      ]);
  }

  public function recoverStale(int $seconds): int {
    $threshold = gmdate('Y-m-d H:i:s', time() - max(60, $seconds));
    $result = $this->reset()->where('status', '=', 'processing')->where('locked_at', '<=', $threshold)->update([
      'status' => 'pending', 'locked_at' => null, 'last_error' => 'La tarea fue recuperada después de superar el tiempo de bloqueo.',
    ]);
    return (int)($result['affected'] ?? 0);
  }
}
