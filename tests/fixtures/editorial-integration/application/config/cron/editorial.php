<?php
$task = (new CronTaskService())->schedule('editorial.publish', EditorialCron::class, gmdate('Y-m-d H:i:s'), [], 60);
if ($task['status'] !== 'success' && $task['code'] !== 'cron_task_exists') throw new RuntimeException('No se pudo registrar el cron editorial.');
