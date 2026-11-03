<?php
require dirname(__DIR__, 2) . '/packages/autoload.php';
$_ENV['MAIL_RATE_LIMIT_ENABLED'] = 'true';
$_ENV['MAIL_RATE_LIMIT_MAX_ATTEMPTS'] = '1';
$_ENV['MAIL_RATE_LIMIT_WINDOW_SECONDS'] = '60';
$database = new PDO('sqlite:' . $argv[1]);
$database->exec('PRAGMA busy_timeout = 2000');
$result = (new \GFrame\Mail\MailRateLimiter($database))->run('contact', '192.0.2.1', static function (): array {
    usleep(200000);
    return ['status' => 'success'];
});
echo $result['code'] ?? $result['status'];
