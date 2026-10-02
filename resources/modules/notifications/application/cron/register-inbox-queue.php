<?php

$key = 'notifications.inbox.queue';
$tasks = new CronDataProvider();
$existing = $tasks->findByKey($key);
if ($existing === null) {
    $result = (new CronTaskService($tasks))->schedule($key, \GFrame\Notifications\InboxQueueCronHandler::class, gmdate('Y-m-d H:i:s'), ['batch' => 120], 60);
    if (($result['status'] ?? '') !== 'success' && ($result['code'] ?? '') !== 'cron_task_exists') error_log('[GFrame Inbox Queue] No se pudo registrar la tarea de inbox.');
} elseif (($existing['status'] ?? '') === 'error') {
    (new CronTaskService($tasks))->reschedule($key, gmdate('Y-m-d H:i:s'));
}
