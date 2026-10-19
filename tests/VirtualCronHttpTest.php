<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class VirtualCronHttpTest extends TestCase
{
    public function testHttpLaunchesDetachedWorkerLoadsRegistrarsAndProtectsAnonymousForms(): void
    {
        $root = sys_get_temp_dir() . '/gframe-http-cron-' . bin2hex(random_bytes(6));
        foreach (['core', 'bin', 'config/routes', 'config/cron', 'app/services', 'storage'] as $directory) mkdir($root . '/' . $directory, 0775, true);
        copy(dirname(__DIR__) . '/resources/modules/cron-runner/application/bin/gframe-cron.php', $root . '/bin/gframe-cron.php');
        $autoload = var_export(dirname(__DIR__) . '/packages/autoload.php', true);
        file_put_contents($root . '/core/Load.php', '<?php require_once ' . $autoload . '; GFrame\Foundation\Bootstrap::boot(dirname(__DIR__));');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false); fclose($socket);
        file_put_contents($root . '/.env', "GFRAME_CRON_MODE=virtual\nGFRAME_CRON_MIN_INTERVAL=1\nGFRAME_PHP_BINARY=\"" . str_replace('\\', '/', PHP_BINARY) . "\"\n");
        file_put_contents($root . '/config/app.php', '<?php return ' . var_export(['app' => ['url' => 'http://' . $address], 'database' => ['default' => 'main', 'connections' => ['main' => ['driver' => 'sqlite', 'path' => $root . '/tasks.sqlite']]]], true) . ';');
        file_put_contents($root . '/storage/gframe-installed.json', json_encode(['modules' => ['cron-runner']]));
        file_put_contents($root . '/app/services/DetachedCronHandler.php', <<<'PHP'
<?php
final class DetachedCronHandler extends Cron {
    public function handle(array $task = []): array {
        file_put_contents(ABSPATH . 'storage/started', 'started');
        $deadline = microtime(true) + 15;
        while (!is_file(ABSPATH . 'storage/release')) {
            if (microtime(true) >= $deadline) throw new RuntimeException('test_release_timeout');
            usleep(10000);
        }
        file_put_contents(ABSPATH . 'storage/finished', 'finished');
        return ['status' => 'success'];
    }
}
PHP);
        file_put_contents($root . '/config/cron/register.php', <<<'PHP'
<?php
$tasks = new CronDataProvider();
if ($tasks->findByKey('http-test') === null) {
    (new CronTaskService($tasks))->schedule('http-test', DetachedCronHandler::class, gmdate('Y-m-d H:i:s'));
}
PHP);
        file_put_contents($root . '/index.php', <<<'PHP'
<?php
require __DIR__ . '/core/Load.php';
GFrame\Cron\VirtualCron::register('web');
if ($_SERVER['REQUEST_URI'] === '/resource') {
    echo GFrame\Security\CsrfToken::fields();
    return;
}
GFrame\Session\SessionRuntime::start(__DIR__);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode((new Middleware())->handle(['middleware' => ['CSRF', 'honeypot']]));
} else {
    echo '<form>' . GFrame\Security\CsrfToken::fields() . '</form>';
}
PHP);
        $database = new \PDO('sqlite:' . $root . '/tasks.sqlite');
        $database->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/sqlite.sql'));
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $server = proc_open([PHP_BINARY, '-S', $address, $root . '/index.php'], [0 => ['pipe', 'r'], 1 => ['file', $null, 'a'], 2 => ['file', $root . '/server.log', 'a']], $pipes, $root);
        self::assertIsResource($server); fclose($pipes[0]);
        try {
            $deadline = microtime(true) + 8;
            do {
                $connection = @stream_socket_client('tcp://' . $address, $number, $message, 0.1);
                if ($connection) { fclose($connection); break; }
                usleep(50000);
            } while (microtime(true) < $deadline);
            $html = file_get_contents('http://' . $address . '/', false, stream_context_create(['http' => ['timeout' => 8]]));
            self::assertFileDoesNotExist($root . '/storage/finished', 'La respuesta no debe esperar al handler.');
            self::assertContains('Cache-Control: private, no-store', $http_response_header);
            preg_match('/Set-Cookie: ([^;]+)/i', implode("\n", $http_response_header), $cookie);
            preg_match('/name="csrfToken" value="([^"]+)"/', $html, $token);
            preg_match('/name="csrfTimestamp" value="([^"]+)"/', $html, $timestamp);
            self::assertNotEmpty($cookie[1]);
            // Liberar tras recibir la respuesta; las peticiones CSRF no forman parte de esta barrera.
            touch($root . '/storage/release');
            $deadline = microtime(true) + 12;
            while (!is_file($root . '/storage/finished') && microtime(true) < $deadline) usleep(50000);
            self::assertFileExists($root . '/storage/finished', file_get_contents($root . '/server.log'));
            $post = static function (array $data, ?string $origin = null) use ($address, $cookie): array {
                $header = "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . $cookie[1] . ($origin === null ? '' : "\r\nOrigin: " . $origin);
                return json_decode(file_get_contents('http://' . $address . '/', false, stream_context_create(['http' => ['method' => 'POST', 'header' => $header, 'content' => http_build_query($data), 'ignore_errors' => true]])), true, 512, JSON_THROW_ON_ERROR);
            };
            $data = ['csrfToken' => $token[1], 'csrfTimestamp' => $timestamp[1]];
            self::assertSame('success', $post($data)['status']);
            self::assertSame('fail_csrf', $post([])['code']);
            self::assertSame('fail_csrf', $post($data, 'https://different.example')['code']);
            self::assertSame('is_bot', $post($data + ['middle_name' => 'bot'])['code']);
            self::assertSame('', file_get_contents('http://' . $address . '/resource'));
            self::assertSame([], array_values(array_filter($http_response_header, static fn(string $value): bool => str_starts_with($value, 'Set-Cookie:'))));
            $deadline = microtime(true) + 4;
            do {
                $status = $database->query('SELECT status FROM cron_tasks')->fetchColumn();
                if ($status === 'completed') break;
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame('completed', $status);
            self::assertSame(1, (int)$database->query('SELECT attempts FROM cron_tasks')->fetchColumn());
            $cli = static function (string $argument, int $expectedExit) use ($root): array {
                $process = proc_open([PHP_BINARY, $root . '/bin/gframe-cron.php', $argument], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $streams);
                fclose($streams[0]);
                $output = stream_get_contents($streams[1]); $error = stream_get_contents($streams[2]);
                fclose($streams[1]); fclose($streams[2]);
                self::assertSame($expectedExit, proc_close($process), $error . $output);
                return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            };
            // La última respuesta puede haber lanzado una pasada vacía todavía pendiente.
            $deadline = microtime(true) + 8;
            do {
                $diagnostic = $cli('--status', 0);
                if (!$diagnostic['data']['busy'] && (int)$diagnostic['data']['state']['pending_until'] === 0) break;
                usleep(20000);
            } while (microtime(true) < $deadline);
            // Las peticiones posteriores pueden registrar una pasada vacía; el diagnóstico es de la última pasada.
            self::assertContains((int)$diagnostic['data']['state']['processed'], [0, 1]);
            self::assertArrayNotHasKey('launch_token', $diagnostic['data']['state']);
            self::assertFalse($diagnostic['data']['busy']);
            self::assertSame(0, (int)$diagnostic['data']['state']['pending_until']);
            self::assertSame(0, $cli('10', 0)['data']['processed']);
            $database->exec("UPDATE cron_tasks SET status = 'pending', handler_class = 'MissingHandler'");
            self::assertSame(1, $cli('10', 1)['data']['failed']);
        } finally {
            proc_terminate($server); proc_close($server);
            touch($root . '/storage/release');
            // Esperar a un worker que ya arrancó antes de retirar su proyecto temporal.
            $deadline = microtime(true) + 4;
            while (is_file($root . '/storage/started') && !is_file($root . '/storage/finished') && microtime(true) < $deadline) usleep(50000);
            unset($database);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            rmdir($root);
        }
    }
}
