<?php

class CronRunner {
  protected function runCron(string $name, callable $callback): array {
    $startedAt = gmdate('Y-m-d H:i:s');

    try {
      $data = $callback();
      return [
        'status' => 'success',
        'cron' => trim($name),
        'started_at' => $startedAt,
        'finished_at' => gmdate('Y-m-d H:i:s'),
        'data' => is_array($data) ? $data : ['result' => $data],
      ];
    } catch (Throwable $e) {
      return [
        'status' => 'error',
        'cron' => trim($name),
        'started_at' => $startedAt,
        'finished_at' => gmdate('Y-m-d H:i:s'),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
      ];
    }
  }

  protected function logCron(string $msg): void {
    echo '[' . gmdate('Y-m-d H:i:s') . " UTC] " . trim($msg) . PHP_EOL;
  }
}
