<?php
require dirname(__DIR__, 2) . '/packages/autoload.php';
\GFrame\Config\ConfigRepository::replace(['auth' => ['rate_limit' => ['login_attempts' => 1]]]);
$deadline = microtime(true) + 10;
while (!is_file($argv[1] . '/go')) {
    if (microtime(true) > $deadline) exit(2);
    usleep(10000);
}
echo json_encode((new \GFrame\Auth\AuthRateLimiter($argv[1]))->reserve('login', 'same@example.test', '192.0.2.1'));
