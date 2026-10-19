<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Cron\CronRuntime;
use GFrame\Cron\VirtualCron;
use PHPUnit\Framework\TestCase;

final class CronCsrfBoundaryTest extends TestCase
{
    private string $root;
    private string $address;
    private mixed $server;
    private \PDO $database;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gframe-cron-csrf-boundary-' . bin2hex(random_bytes(6));
        foreach (['core', 'config/routes', 'config/cron', 'app/services', 'storage'] as $directory) {
            mkdir($this->root . '/' . $directory, 0775, true);
        }
        $autoload = var_export(dirname(__DIR__) . '/packages/autoload.php', true);
        file_put_contents($this->root . '/core/Load.php', '<?php require_once ' . $autoload . '; GFrame\Foundation\Bootstrap::boot(dirname(__DIR__));');
        copy(__DIR__ . '/fixtures/cron-csrf-boundary.php', $this->root . '/index.php');
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $this->address = stream_socket_get_name($listener, false);
        fclose($listener);
        file_put_contents($this->root . '/config/app.php', '<?php return ' . var_export([
            'app' => ['url' => 'http://' . $this->address],
            'database' => ['default' => 'main', 'connections' => ['main' => ['driver' => 'sqlite', 'path' => $this->root . '/tasks.sqlite']]],
        ], true) . ';');
        file_put_contents($this->root . '/storage/gframe-installed.json', '{"modules":["cron-runner"]}');
        $this->database = new \PDO('sqlite:' . $this->root . '/tasks.sqlite');
        $this->database->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/sqlite.sql'));
        $this->environment('real');
        $environment = getenv();
        foreach (array_keys($environment) as $key) {
            if (str_starts_with($key, 'GFRAME_CRON_') || $key === 'GFRAME_PHP_BINARY') unset($environment[$key]);
        }
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $this->server = proc_open([PHP_BINARY, '-S', $this->address, $this->root . '/index.php'], [0 => ['pipe', 'r'], 1 => ['file', $null, 'a'], 2 => ['file', $this->root . '/server.log', 'a']], $pipes, $this->root, $environment);
        self::assertIsResource($this->server);
        fclose($pipes[0]);
        $deadline = microtime(true) + 8;
        do {
            $connection = @stream_socket_client('tcp://' . $this->address, $number, $message, 0.1);
            if ($connection) { fclose($connection); return; }
            usleep(50000);
        } while (microtime(true) < $deadline);
        self::fail('El servidor temporal no arrancó.');
    }

    protected function tearDown(): void
    {
        if (isset($this->server) && is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        unset($this->database);
        if (!isset($this->root) || !is_dir($this->root)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        rmdir($this->root);
    }

    private function environment(string $mode, ?string $binary = null): void
    {
        $binary ??= PHP_BINARY;
        file_put_contents($this->root . '/.env', "GFRAME_CRON_MODE={$mode}\nGFRAME_CRON_EXECUTOR=cli\nGFRAME_CRON_MIN_INTERVAL=1\nGFRAME_PHP_BINARY=\"" . str_replace('\\', '/', $binary) . "\"\n");
    }

    private function request(string $path, ?array $data = null, ?string $cookie = null): array
    {
        $headers = 'Content-Type: application/x-www-form-urlencoded';
        if ($cookie !== null) $headers .= "\r\nCookie: " . $cookie;
        $body = file_get_contents('http://' . $this->address . $path, false, stream_context_create(['http' => [
            'method' => $data === null ? 'GET' : 'POST', 'header' => $headers,
            'content' => $data === null ? '' : http_build_query($data), 'timeout' => 10, 'ignore_errors' => true,
        ]]));
        self::assertIsString($body);
        return [$body, $http_response_header];
    }

    public function testSessionSurvivesTwoTabsAndRecoversAfterItsStoredStateIsLost(): void
    {
        [$body, $headers] = $this->request('/token');
        $first = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        preg_match('/Set-Cookie: ([^;]+)/i', implode("\n", $headers), $match);
        self::assertNotEmpty($match[1]);
        $cookie = $match[1];
        self::assertContains('Cache-Control: private, no-store', $headers);
        $second = json_decode($this->request('/token', cookie: $cookie)[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($first, $second);
        $fields = ['csrfToken' => $first['token'], 'csrfTimestamp' => $first['timestamp']];
        $submit = fn(array $data, ?string $session): array => json_decode($this->request('/submit', $data, $session)[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $submit($fields, $cookie)['status']);
        $rejected = $submit($fields, null);
        self::assertSame('unauthorized', $rejected['status']);
        self::assertContains($rejected['code'], ['fail_csrf', 'to_reload']);
        $this->request('/expire', [], $cookie);
        $rejected = $submit($fields, $cookie);
        self::assertSame('unauthorized', $rejected['status']);
        self::assertContains($rejected['code'], ['fail_csrf', 'to_reload']);
        [$body, $headers] = $this->request('/token', cookie: $cookie);
        $recovered = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        self::assertNotSame($first['token'], $recovered['token']);
        preg_match('/Set-Cookie: ([^;]+)/i', implode("\n", $headers), $newCookie);
        self::assertSame('success', $submit(['csrfToken' => $recovered['token'], 'csrfTimestamp' => $recovered['timestamp']], $newCookie[1] ?? $cookie)['status']);
    }

    public function testInfrastructureFailuresKeepHttpResponseAndThrottleLogs(): void
    {
        $this->environment('virtual', $this->root . '/missing-php');
        [$body, $headers] = $this->request('/public');
        self::assertSame('response_preserved', $body);
        self::assertContains('X-Cron-Registered: yes', $headers);
        self::assertSame([], preg_grep('/^Set-Cookie:/i', $headers));
        // Una nueva petición espera al cierre anterior del servidor PHP de un solo proceso.
        $this->request('/admin');
        self::assertNull($this->database->query('SELECT launch_token FROM cron_runtime')->fetchColumn());
        self::assertStringContainsString('PHP CLI', $this->database->query('SELECT last_error FROM cron_runtime')->fetchColumn());
        self::assertSame(0, (int)$this->database->query('SELECT COUNT(*) FROM cron_tasks')->fetchColumn());
        $this->database->exec('DROP TABLE cron_runtime');
        self::assertSame('response_preserved', $this->request('/public')[0]);
        self::assertSame('response_preserved', $this->request('/admin')[0]);
        $this->request('/api');
        self::assertSame(1, substr_count(file_get_contents($this->root . '/cron.log'), '[gframe.cron]'));
    }

    public function testRealAndExcludedChannelsIgnoreClientExecutionParameters(): void
    {
        $hostile = ['handler' => 'ClientSelectedHandler', 'file' => $this->root . '/storage/canary', 'payload' => 'untrusted', 'limit' => 999999];
        foreach (['/public', '/admin', '/ajax'] as $path) {
            [$body, $headers] = $this->request($path . '?' . http_build_query($hostile), $hostile, 'handler=ClientSelectedHandler');
            self::assertSame('response_preserved', $body);
            self::assertContains('X-Cron-Registered: no', $headers);
        }
        $this->environment('virtual');
        foreach (['/api', '/sse'] as $path) {
            [$body, $headers] = $this->request($path, $hostile);
            self::assertSame('response_preserved', $body);
            self::assertContains('X-Cron-Registered: no', $headers);
        }
        self::assertSame(0, (int)$this->database->query('SELECT last_launch_at FROM cron_runtime')->fetchColumn());
        self::assertSame(0, (int)$this->database->query('SELECT COUNT(*) FROM cron_tasks')->fetchColumn());
        self::assertFileDoesNotExist($this->root . '/storage/canary');
        file_put_contents($this->root . '/app/services/TrustedBoundaryHandler.php', '<?php final class TrustedBoundaryHandler extends Cron { public function handle(array $task=[]): array { file_put_contents(ABSPATH."storage/trusted", json_encode($task)); return ["status"=>"success"]; } }');
        file_put_contents($this->root . '/app/services/ClientSelectedHandler.php', '<?php final class ClientSelectedHandler extends Cron { public function handle(array $task=[]): array { touch(ABSPATH."storage/canary"); return ["status"=>"success"]; } }');
        $this->database->exec("INSERT INTO cron_tasks(task_key,handler_class,payload_json,scheduled_at) VALUES ('trusted','TrustedBoundaryHandler','{\"source\":\"server\"}','2000-01-01 00:00:00')");
        [$body, $headers] = $this->request('/public?' . http_build_query($hostile), $hostile, 'handler=ClientSelectedHandler');
        self::assertSame('response_preserved', $body);
        self::assertContains('X-Cron-Registered: yes', $headers);
        $deadline = microtime(true) + 12;
        do {
            if ($this->database->query('SELECT status FROM cron_tasks')->fetchColumn() === 'completed') break;
            usleep(50000);
        } while (microtime(true) < $deadline);
        self::assertSame('completed', $this->database->query('SELECT status FROM cron_tasks')->fetchColumn());
        self::assertSame(['source' => 'server'], json_decode(file_get_contents($this->root . '/storage/trusted'), true, flags: JSON_THROW_ON_ERROR));
        self::assertFileDoesNotExist($this->root . '/storage/canary');
        self::assertSame('TrustedBoundaryHandler', $this->database->query('SELECT handler_class FROM cron_tasks')->fetchColumn());
        self::assertSame(1, (int)$this->database->query('SELECT attempts FROM cron_tasks')->fetchColumn());
    }

    public function testSettingsAcceptOnlyTheirDocumentedBoundariesAndCliDoesNotRegister(): void
    {
        $previous = $_ENV;
        $configuration = ConfigRepository::get();
        try {
            ConfigRepository::replace(['cron' => ['mode' => 'virtual', 'executor' => 'cli']]);
            $_ENV['GFRAME_CRON_MODE'] = 'virtual';
            $_ENV['GFRAME_CRON_EXECUTOR'] = 'cli';
            foreach ([[1, 1], [86400, 100]] as [$interval, $limit]) {
                $_ENV['GFRAME_CRON_MIN_INTERVAL'] = $interval;
                $_ENV['GFRAME_CRON_BATCH_LIMIT'] = $limit;
                self::assertSame($interval, CronRuntime::settings()['min_interval']);
                self::assertSame($limit, CronRuntime::settings()['batch_limit']);
            }
            foreach (['GFRAME_CRON_MIN_INTERVAL' => [0, 86401, 'unlimited'], 'GFRAME_CRON_BATCH_LIMIT' => [0, 101, 'unlimited']] as $key => $invalid) {
                foreach ($invalid as $value) {
                    $_ENV['GFRAME_CRON_MIN_INTERVAL'] = 1;
                    $_ENV['GFRAME_CRON_BATCH_LIMIT'] = 1;
                    $_ENV[$key] = $value;
                    $called = false;
                    self::assertSame('cron_unavailable', VirtualCron::kick(null, static function () use (&$called): void { $called = true; })['code']);
                    self::assertFalse($called);
                }
            }
            VirtualCron::register('web');
            self::assertFalse((new \ReflectionProperty(VirtualCron::class, 'registered'))->getValue());
        } finally {
            $_ENV = $previous;
            ConfigRepository::replace($configuration);
        }
    }
}
