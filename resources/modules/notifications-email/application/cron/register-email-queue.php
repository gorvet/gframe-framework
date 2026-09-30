<?php

use GFrame\Notifications\Email\EmailQueueCronHandler;

$taskKey = 'notifications.email.queue';
$tasks = new CronDataProvider();
$existing = $tasks->findByKey($taskKey);
if ($existing === null) {
    $result = (new CronTaskService($tasks))->schedule($taskKey, EmailQueueCronHandler::class, gmdate('Y-m-d H:i:s'), ['batch' => 120], 60);
    if (($result['status'] ?? '') !== 'success' && ($result['code'] ?? '') !== 'cron_task_exists') {
        error_log('[GFrame Email Queue] No se pudo registrar la tarea de correo.');
    }
} elseif (($existing['status'] ?? '') === 'error') {
    (new CronTaskService($tasks))->reschedule($taskKey, gmdate('Y-m-d H:i:s'));
}
