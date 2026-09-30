<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/core/Load.php';

foreach (glob($projectRoot . '/config/cron/*.php') ?: [] as $registration) {
    require $registration;
}

$limit = isset($argv[1]) ? max(1, min(100, (int)$argv[1])) : 10;
$result = (new CronScheduler())->runDue($limit);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(($result['status'] ?? '') === 'success' ? 0 : 1);
