<?php

class CronDataProvider extends ORM {
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
    'is_active',
  ];

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

  public function markProcessing(int $taskID): array {
    return $this->reset()
      ->queryTable($this->table)
      ->where('task_id', '=', $taskID)
      ->update([
        'status' => 'processing',
        'locked_at' => gmdate('Y-m-d H:i:s'),
      ]);
  }

  public function markSuccess(int $taskID): array {
    return $this->reset()
      ->queryTable($this->table)
      ->where('task_id', '=', $taskID)
      ->update([
        'status' => 'pending',
        'locked_at' => null,
        'last_error' => null,
        'last_run_at' => gmdate('Y-m-d H:i:s'),
      ]);
  }

  public function markFailed(int $taskID, string $message): array {
    return $this->reset()
      ->queryTable($this->table)
      ->where('task_id', '=', $taskID)
      ->update([
        'status' => 'error',
        'locked_at' => null,
        'last_error' => trim($message),
        'last_run_at' => gmdate('Y-m-d H:i:s'),
      ]);
  }
}
