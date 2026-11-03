<?php
require dirname(__DIR__, 2) . '/packages/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^gframe_rate_test_[a-f0-9]+$/', $input['schema'])) exit(2);
$pdo = new PDO(getenv('GFRAME_TEST_MYSQL_DSN'), getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('USE `' . $input['schema'] . '`');
(new ReflectionProperty(DatabaseManager::class, 'pool'))->setValue(null, ['rate-test' => $pdo]);
\GFrame\Config\ConfigRepository::replace([
    'auth' => ['rate_limit' => ['connection' => 'rate-test', 'login_attempts' => 1]],
    'mail' => ['rate_limit' => ['connection' => 'rate-test', 'max_attempts' => 1]],
]);
$result = $input['kind'] === 'auth'
    ? (new \GFrame\Auth\AuthRateLimiter())->reserve('login', 'user@example.test', 'ip')
    : (new \GFrame\Mail\MailRateLimiter())->run('contact', 'ip', static function (): array {
        usleep(200000);
        return ['status' => 'success'];
    });
echo json_encode($result, JSON_THROW_ON_ERROR);
