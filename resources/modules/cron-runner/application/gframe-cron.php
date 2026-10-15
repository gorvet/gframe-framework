<?php

declare(strict_types=1);

require __DIR__ . '/core/Load.php';

// Este punto de entrada no pasa por Router y no puede registrar el disparador virtual.
ignore_user_abort(true);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$result = \GFrame\Cron\CronHttpEndpoint::handle($_SERVER);
http_response_code($result['http_code']);
unset($result['http_code']);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
