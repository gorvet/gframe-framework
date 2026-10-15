<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

/** Integración opcional: TLS, proxy y pool PHP-FPM reales, sin servicios externos. */
final class CronHttpsFpmTest extends TestCase
{
    public function testHttpsCronWithRealFpmAndVerifiedCertificates(): void
    {
        $fpm = (string)getenv('GFRAME_TEST_PHP_FPM');
        $nginx = (string)getenv('GFRAME_TEST_NGINX');
        if (!is_file($fpm) || !is_file($nginx)) self::markTestSkipped('Requiere PHP-FPM y Nginx para la integración HTTPS.');

        $root = sys_get_temp_dir() . '/gframe-https-fpm-' . bin2hex(random_bytes(6));
        foreach (['core', 'config/routes', 'config/cron', 'app/services', 'storage', 'logs'] as $directory) mkdir($root . '/' . $directory, 0775, true);
        $httpPort = $this->freePort();
        $fpmPort = $this->freePort();
        $base = 'https://127.0.0.1:' . $httpPort;
        $secret = bin2hex(random_bytes(32));
        $processes = [];
        $database = null;
        try {
            $this->command(['/usr/bin/openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', $root . '/key.pem', '-out', $root . '/cert.pem', '-days', '1', '-subj', '/CN=127.0.0.1', '-addext', 'subjectAltName=IP:127.0.0.1']);
            $autoload = var_export(dirname(__DIR__) . '/packages/autoload.php', true);
            file_put_contents($root . '/core/Load.php', '<?php require_once ' . $autoload . '; GFrame\Foundation\Bootstrap::boot(dirname(__DIR__));');
            copy(dirname(__DIR__) . '/resources/modules/cron-runner/application/gframe-cron.php', $root . '/gframe-cron.php');
            $routes = file_get_contents(dirname(__DIR__) . '/resources/skeleton/nginx.conf');
            $routes = preg_replace_callback('/^# location = \/gframe-cron\.php \{.*?^# \}/ms', static fn(array $match): string => preg_replace('/^# ?/m', '', $match[0]), $routes);
            $routes = str_replace('fastcgi_pass unix:/run/php/php8.3-fpm.sock;', 'fastcgi_pass 127.0.0.1:' . $fpmPort . ';', $routes);
            $routes = str_replace('include fastcgi_params;', 'include /etc/nginx/fastcgi_params;', $routes);
            file_put_contents($root . '/nginx-routes.conf', $routes);
            file_put_contents($root . '/config/app.php', '<?php return ' . var_export(['app' => ['url' => $base], 'database' => ['default' => 'main', 'connections' => ['main' => ['driver' => 'sqlite', 'path' => $root . '/tasks.sqlite']]]], true) . ';');
            file_put_contents($root . '/storage/gframe-installed.json', '{"modules":["cron-runner"]}');
            $environment = "GFRAME_CRON_EXECUTOR=http\nGFRAME_CRON_HTTP_SECRET={$secret}\nAPP_URL={$base}\nGFRAME_CRON_BATCH_LIMIT=1\nGFRAME_CRON_MIN_INTERVAL=60\n";
            file_put_contents($root . '/.env', $environment . "GFRAME_CRON_MODE=real\n");
            file_put_contents($root . '/app/services/HttpsCronHandler.php', <<<'PHP'
<?php
final class HttpsCronHandler extends Cron {
    public function handle(array $task = []): array {
        if (is_file(ABSPATH . 'storage/hold')) {
            file_put_contents(ABSPATH . 'storage/started', 'started');
            $deadline = microtime(true) + 20;
            while (!is_file(ABSPATH . 'storage/release')) {
                if (microtime(true) >= $deadline) throw new RuntimeException('https_test_release_timeout');
                usleep(10000);
            }
        }
        file_put_contents(ABSPATH . 'storage/effect', 'x', FILE_APPEND);
        return ['status' => 'success'];
    }
}
PHP);
            file_put_contents($root . '/index.php', <<<'PHP'
<?php
require __DIR__ . '/core/Load.php';
GFrame\Session\SessionRuntime::start(__DIR__);
if (!isset($_GET['probe'])) GFrame\Cron\VirtualCron::register('web');
header('Content-Type: application/json');
echo json_encode(['fpm' => PHP_SAPI === 'fpm-fcgi', 'finish_request' => function_exists('fastcgi_finish_request')]);
PHP);
            $database = new \PDO('sqlite:' . $root . '/tasks.sqlite');
            $database->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/sqlite.sql'));
            $database->exec("INSERT INTO cron_tasks(task_key,handler_class,payload_json,scheduled_at) VALUES ('https','HttpsCronHandler','{}','2000-01-01 00:00:00')");
            file_put_contents($root . '/fpm.conf', "[global]\ndaemonize = no\npid = {$root}/fpm.pid\nerror_log = {$root}/logs/fpm.log\n[cron-test]\nuser = nobody\ngroup = nogroup\nlisten = 127.0.0.1:{$fpmPort}\npm = static\npm.max_children = 3\nclear_env = yes\ncatch_workers_output = yes\nphp_admin_value[openssl.cafile] = {$root}/cert.pem\nphp_admin_value[opcache.enable] = 0\nphp_admin_value[error_log] = {$root}/logs/php.log\nphp_admin_flag[log_errors] = on\n");
            file_put_contents($root . '/nginx.conf', "worker_processes 1; daemon off; master_process off; pid {$root}/nginx.pid; error_log {$root}/logs/nginx.log; events { worker_connections 64; } http { access_log {$root}/logs/access.log; client_body_temp_path {$root}/body; fastcgi_temp_path {$root}/fastcgi; server { listen 127.0.0.1:{$httpPort} ssl; server_name localhost; ssl_certificate {$root}/cert.pem; ssl_certificate_key {$root}/key.pem; root {$root}; include {$root}/nginx-routes.conf; location ~ \\.php$ { try_files \$uri =404; include /etc/nginx/fastcgi_params; fastcgi_pass 127.0.0.1:{$fpmPort}; fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; fastcgi_param HTTP_AUTHORIZATION \$http_authorization; fastcgi_intercept_errors off; } } }");
            foreach ([[$fpm, '-F', '-y', $root . '/fpm.conf'], [$nginx, '-p', $root . '/', '-c', $root . '/nginx.conf']] as $command) {
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $root . '/logs/process.log', 'a'], 2 => ['file', $root . '/logs/process.log', 'a']], $pipes, $root);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = $process;
            }
            $this->waitFor(fn(): bool => $this->portReady($httpPort) && $this->portReady($fpmPort), $root);
            $request = static function (string $path, string $method = 'GET', string $headers = '') use ($base, $root): array {
                $body = file_get_contents($base . $path, false, stream_context_create(['ssl' => ['cafile' => $root . '/cert.pem', 'verify_peer' => true, 'verify_peer_name' => true], 'http' => ['method' => $method, 'header' => $headers, 'ignore_errors' => true, 'timeout' => 5]]));
                preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
                return [(int)$status[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR), $http_response_header];
            };
            [$status, $data] = $request('/');
            self::assertSame(200, $status);
            self::assertSame(['fpm' => true, 'finish_request' => true], $data);
            self::assertNull($database->query('SELECT last_launch_at FROM cron_runtime')->fetchColumn());
            self::assertFileDoesNotExist($root . '/storage/effect');
            self::assertSame(405, $request('/gframe-cron.php')[0]);
            self::assertSame(403, $request('/gframe-cron.php', 'POST', 'Authorization: Bearer wrong')[0]);
            self::assertFileDoesNotExist($root . '/storage/effect');
            [$status, $data, $headers] = $request('/gframe-cron.php', 'POST', 'Authorization: Bearer ' . $secret);
            self::assertSame(200, $status);
            self::assertSame('cron_processed', $data['code']);
            self::assertSame([], array_values(preg_grep('/^Set-Cookie:/i', $headers)));
            self::assertSame('x', file_get_contents($root . '/storage/effect'));

            // Rechazos TLS reales del lanzador, antes de transmitir credenciales o tickets.
            file_put_contents($root . '/tls-client.php', '<?php require ' . $autoload . '; $_ENV["APP_URL"]=$argv[1]; $_ENV["GFRAME_CRON_HTTP_SECRET"]="' . $secret . '"; try {(new GFrame\Cron\CronHttpLauncher())->launch(str_repeat("b",48)); exit(7);} catch(RuntimeException $e) {exit(0);}');
            $this->command([PHP_BINARY, '-d', 'openssl.cafile=', $root . '/tls-client.php', $base]);
            self::assertSame('127.0.0.1', gethostbyname('localhost'));
            $this->command([PHP_BINARY, '-d', 'openssl.cafile=' . $root . '/cert.pem', $root . '/tls-client.php', 'https://localhost:' . $httpPort]);
            self::assertSame('x', file_get_contents($root . '/storage/effect'));

            $database->exec("UPDATE cron_tasks SET status='pending'; INSERT INTO cron_tasks(task_key,handler_class,payload_json,scheduled_at) VALUES ('later','HttpsCronHandler','{}','2000-01-01 00:00:00')");
            file_put_contents($root . '/.env', $environment . "GFRAME_CRON_MODE=virtual\n");
            self::assertSame(403, $request('/gframe-cron.php', 'POST', 'Authorization: Bearer ' . $secret)[0]);
            [$status, $data] = $request('/gframe-cron.php', 'POST', 'Authorization: Bearer ' . $secret . "\r\nX-GFrame-Cron-Ticket: " . str_repeat('d', 48));
            self::assertSame('cron_skipped', $data['code']);
            self::assertSame('x', file_get_contents($root . '/storage/effect'));
            touch($root . '/storage/hold');
            [$status, $data, $headers] = $request('/');
            self::assertSame(200, $status);
            self::assertTrue($data['finish_request']);
            $this->waitFor(fn(): bool => is_file($root . '/storage/started'), $root);
            self::assertSame('x', file_get_contents($root . '/storage/effect'), 'El cliente recibe la respuesta antes de liberar el handler.');
            preg_match('/^Set-Cookie: ([^;]+)/mi', implode("\n", $headers), $cookie);
            self::assertNotEmpty($cookie[1]);
            self::assertSame(200, $request('/?probe=1', 'GET', 'Cookie: ' . $cookie[1])[0]);
            self::assertSame('x', file_get_contents($root . '/storage/effect'), 'La sesión queda disponible mientras el handler espera.');
            touch($root . '/storage/release');
            $this->waitFor(fn(): bool => (int)$database->query("SELECT COUNT(*) FROM cron_tasks WHERE status='completed'")->fetchColumn() === 1, $root);
            self::assertSame('xx', file_get_contents($root . '/storage/effect'));
            self::assertSame(1, (int)$database->query("SELECT COUNT(*) FROM cron_tasks WHERE status='pending'")->fetchColumn());
            self::assertSame(2, (int)$database->query('SELECT SUM(attempts) FROM cron_tasks')->fetchColumn());
        } finally {
            touch($root . '/storage/release');
            foreach (array_reverse($processes) as $process) { proc_terminate($process); proc_close($process); }
            unset($database);
            $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($entries as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            rmdir($root);
        }
    }

    private function command(array $command): void
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output . $errors);
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        return $port;
    }

    private function portReady(int $port): bool
    {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $error, $message, .1);
        if (!$socket) return false;
        fclose($socket);
        return true;
    }

    private function waitFor(callable $condition, string $root): void
    {
        $deadline = microtime(true) + 8;
        do { if ($condition()) return; usleep(20000); } while (microtime(true) < $deadline);
        $logs = '';
        foreach (glob($root . '/logs/*.log') as $file) $logs .= file_get_contents($file);
        self::fail('No se alcanzó el estado esperado: ' . $logs);
    }
}
