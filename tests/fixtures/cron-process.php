<?php

require dirname(__DIR__, 2) . '/packages/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
define('DB_DEFAULT_CONNECTION', 'cron_test');
define('DB_CONNECTIONS', ['cron_test' => $config]);
$database = DatabaseManager::connection();
if (!$database instanceof PDO) throw new RuntimeException('test_connection_failed');

final class CronProcessHandler extends Cron
{
    public function handle(array $task = []): array
    {
        $database = DatabaseManager::connection();
        $database->exec('INSERT INTO cron_effects (effect) VALUES (1)');
        if (!empty($task['old_lock'])) $database->exec("UPDATE cron_tasks SET locked_at = '2000-01-01 00:00:00' WHERE status = 'processing'");
        if (!empty($task['sleep'])) usleep((int)$task['sleep'] * 1000);
        if (!empty($task['crash'])) exit(23);
        return ['status' => 'success'];
    }
}

if (isset($argv[3])) {
    $deadline = microtime(true) + 10;
    while (!is_file($argv[3])) {
        if (microtime(true) > $deadline) exit(24);
        usleep(10000);
    }
}
$runtime = new GFrame\Cron\CronRuntime($database);
if ($argv[2] === 'reserve') {
    echo json_encode(['token' => $runtime->reserveLaunch(60, time())]);
} else {
    $virtual = str_starts_with($argv[2], 'virtual:');
    echo json_encode((new CronScheduler(runtime: $runtime))->runDue($virtual ? 10 : (int)$argv[2], $virtual ? substr($argv[2], 8) : null));
}
