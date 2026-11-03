<?php
require dirname(__DIR__, 2) . '/packages/autoload.php';
\GFrame\Config\ConfigRepository::replace(['auth' => ['rate_limit' => ['driver' => 'database', 'login_attempts' => 1]]]);
$database = new PDO('sqlite:' . $argv[1]);
echo json_encode((new \GFrame\Auth\AuthRateLimiter($database))->reserve('login', 'same@example.test', '192.0.2.1'));