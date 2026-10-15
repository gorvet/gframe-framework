<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/core/Load.php';

try {
    if (($argv[1] ?? '') === '--status') {
        $result = ['status' => 'success', 'data' => \GFrame\Cron\CronRuntime::frameworkDefault()->diagnostic()];
    } else {
        $settings = \GFrame\Cron\CronRuntime::settings();
        $limit = isset($argv[1]) ? max(1, min(100, (int)$argv[1])) : $settings['batch_limit'];
        $result = \GFrame\Cron\CronExecutor::run($limit);
    }
} catch (Throwable $exception) {
    $result = ['status' => 'error', 'code' => 'cron_unavailable', 'message' => $exception->getMessage()];
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(($result['status'] ?? '') === 'success' && (int)($result['data']['failed'] ?? 0) === 0 ? 0 : 1);
