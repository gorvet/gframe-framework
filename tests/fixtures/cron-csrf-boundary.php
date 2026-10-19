<?php

require __DIR__ . '/core/Load.php';
ini_set('error_log', __DIR__ . '/cron.log');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$channel = match ($path) {
    '/api' => 'api',
    '/sse' => 'sse',
    '/ajax' => 'ajax',
    default => 'web',
};
GFrame\Cron\VirtualCron::register($channel);

if (in_array($path, ['/token', '/submit', '/expire'], true)) {
    GFrame\Session\SessionRuntime::start(__DIR__);
    header('Content-Type: application/json');
    if ($path === '/expire') {
        session_destroy();
        echo json_encode(['status' => 'expired']);
    } elseif ($path === '/submit') {
        echo json_encode((new Middleware())->handle(['middleware' => ['CSRF']]));
    } else {
        echo json_encode(['token' => $_SESSION['csrfToken'], 'timestamp' => $_SESSION['csrfTimestamp']]);
    }
    return;
}

header('X-Cron-Registered: ' . ((new ReflectionProperty(GFrame\Cron\VirtualCron::class, 'registered'))->getValue() ? 'yes' : 'no'));
echo 'response_preserved';
