<?php

namespace GFrame\Tests;

use GFrame\Install\ProjectInstaller;
use PHPUnit\Framework\TestCase;

final class ServerRoutingTest extends TestCase
{
    public function testOnlyTrustedServerParametersSelectServerErrors(): void
    {
        $responder = new \ErrorResponder();
        foreach ([403, 404, 500, 503] as $status) {
            self::assertSame($status, $responder->serverErrorStatus(['GFRAME_SERVER_ERROR' => (string)$status]));
            self::assertSame($status, $responder->serverErrorStatus(['REDIRECT_STATUS' => (string)$status]));
        }
        foreach (['', '0', '200', '502', '404evil'] as $value) self::assertNull($responder->serverErrorStatus(['GFRAME_SERVER_ERROR' => $value, 'REDIRECT_STATUS' => '200']));
        self::assertNull($responder->serverErrorStatus(['HTTP_GFRAME_SERVER_ERROR' => '500', 'HTTP_REDIRECT_STATUS' => '403', 'QUERY_STRING' => 'error_code=500']));
    }

    public function testNginxFragmentDoesNotContainProjectOrPanelConfiguration(): void
    {
        $config = (string)file_get_contents(dirname(__DIR__) . '/resources/skeleton/nginx.conf');
        self::assertStringContainsString('# location = /gframe-cron.php {', $config);
        self::assertStringContainsString('#     fastcgi_pass unix:/run/php/php8.3-fpm.sock;', $config);
        self::assertStringContainsString('#     fastcgi_ignore_client_abort on;', $config);
        $config = preg_replace('/^\s*#.*$/m', '', $config);
        foreach (['server_name ', 'listen ', 'ssl_certificate', 'enable-php-81', 'codice.', 'botzy.', 'Access-Control-Allow-Origin', 'http_x_requested_with', 'http_x_webhook_wapi', 'index.php/$1', 'error_code='] as $projectSpecific) self::assertStringNotContainsString($projectSpecific, $config);
        foreach (['fastcgi_pass ', 'fastcgi_param ', '$gframe_php', 'location = /index.php', 'location = /install.php'] as $phpHandler) self::assertStringNotContainsString($phpHandler, $config);
        self::assertStringContainsString('nginx\\.conf|composer', $config);
        self::assertStringContainsString('if ($gframe_server_error = "")', $config);
        foreach ([403, 404, 500, 503] as $status) self::assertStringContainsString('error_page ' . $status . ' = @gframe_error' . $status . ';', $config);
    }

    public function testRealNginxRoutesRequestsAndServerErrorsToTheInstalledFramework(): void
    {
        $nginx = (string)getenv('GFRAME_TEST_NGINX');
        $cgi = (string)getenv('GFRAME_TEST_PHP_CGI');
        if (!is_file($nginx) || !is_file($cgi)) self::markTestSkipped('Requiere GFRAME_TEST_NGINX y GFRAME_TEST_PHP_CGI.');
        $root = sys_get_temp_dir() . '/gframe-nginx-test-' . bin2hex(random_bytes(6));
        mkdir($root . '/logs', 0775, true);
        mkdir($root . '/temp');
        mkdir($root . '/project');
        $root = str_replace('\\', '/', (string)realpath($root));
        $project = $root . '/project';
        $httpPort = $this->freePort();
        $cgiPort = $this->freePort();
        $processes = [];
        try {
            $result = ProjectInstaller::frameworkDefault()->install(['project_root' => $project, 'profile' => 'managed', 'app_name' => 'Nginx test', 'app_url' => 'http://127.0.0.1:' . $httpPort, 'database' => ['driver' => 'sqlite', 'path' => 'storage/database.sqlite'], 'superadministrator' => ['email' => 'test@example.test', 'password' => 'Password-123']]);
            self::assertSame('success', $result['status']);
            mkdir($project . '/packages');
            file_put_contents($project . '/packages/autoload.php', '<?php require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';');
            $this->addVirtualAppRoute($project);
            foreach (['app/private.json', 'app/private.txt', 'public/wcapi/sdk.js', 'uploads/library/2026/10/photo.jpg', 'uploads/user/2/library/2026/10/photo.jpg', 'uploads/tenant/3/library/2026/10/photo.jpg', 'uploads/procedimientos/private.pdf', 'downloads/private.zip', 'download/private.zip', 'backup.zip', 'data.json'] as $fixture) {
                if (!is_dir(dirname($project . '/' . $fixture))) mkdir(dirname($project . '/' . $fixture), 0775, true);
                file_put_contents($project . '/' . $fixture, 'server-fixture-' . $fixture);
            }
            $params = $root . '/fastcgi_params';
            $serverParams = dirname($nginx) . '/conf/fastcgi_params';
            if (!is_file($serverParams)) $serverParams = '/etc/nginx/fastcgi_params';
            self::assertFileExists($serverParams);
            copy($serverParams, $params);
            file_put_contents($root . '/nginx.conf', 'worker_processes 1; daemon off; master_process off; pid logs/nginx.pid; error_log logs/error.log; events { worker_connections 64; } http { access_log off; server { listen 127.0.0.1:' . $httpPort . '; server_name localhost; root "' . $project . '"; include "' . $project . '/nginx.conf"; location ~ \\.php$ { try_files $uri =404; fastcgi_pass 127.0.0.1:' . $cgiPort . '; include "' . $params . '"; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_param GFRAME_SERVER_ERROR $gframe_server_error; fastcgi_intercept_errors off; } location = /test500 { return 500; } location = /test503 { return 503; } } }');
            $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            foreach ([[$cgi, '-b', '127.0.0.1:' . $cgiPort, '-d', 'cgi.fix_pathinfo=0'], [$nginx, '-p', $root . '/', '-c', 'nginx.conf']] as $command) {
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $null, 'a'], 2 => ['file', $root . '/logs/process.log', 'a']], $pipes, $root);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = $process;
            }
            $this->waitPort($cgiPort, $root . '/logs/process.log');
            $this->waitPort($httpPort, $root . '/logs/process.log');
            $base = 'http://127.0.0.1:' . $httpPort;
            [$status, $body] = $this->request($base . '/');
            self::assertSame(200, $status);
            self::assertStringContainsString('Algo maravilloso se construye aquí.', $body);
            self::assertSame(200, $this->request($base . '/login')[0]);
            [$status, $body] = $this->request($base . '/app/recordatorios');
            self::assertSame(200, $status);
            self::assertStringContainsString('Algo maravilloso se construye aquí.', $body);
            $this->assertPublicWorkerDoesNotStartSession($base);
            self::assertSame(200, $this->request($base . '/public/css/common.css')[0]);
            foreach (['public/wcapi/sdk.js', 'uploads/library/2026/10/photo.jpg', 'uploads/user/2/library/2026/10/photo.jpg', 'uploads/tenant/3/library/2026/10/photo.jpg'] as $fixture) {
                [$status, $body] = $this->request($base . '/' . $fixture);
                self::assertSame(200, $status, $fixture);
                self::assertSame('server-fixture-' . $fixture, $body);
            }
            foreach (['app/private.json', 'app/private.txt', 'uploads/procedimientos/private.pdf', 'downloads/private.zip', 'download/private.zip', 'backup.zip', 'data.json'] as $fixture) {
                [$status, $body] = $this->request($base . '/' . $fixture);
                self::assertContains($status, [403, 404], $fixture);
                self::assertStringNotContainsString('server-fixture-', $body);
            }
            self::assertSame(200, $this->request($base . '/?error_code=500', 'GET', '', ['Gframe-Server-Error: 500', 'Redirect-Status: 500'])[0]);
            foreach (['/app/controllers/home/HomeController.php' => 403, '/app/missing' => 404, '/config/app.php' => 403, '/core/Load.php' => 403, '/.env' => 403, '/public/evil.php' => 403, '/unknown' => 404, '/public/missing.css' => 404, '/test500' => 500, '/test503' => 503] as $path => $expected) {
                [$status, $body] = $this->request($base . $path, 'GET', '', ['X-Requested-With: XMLHttpRequest', 'X-Webhook-Wapi: fake']);
                self::assertSame($expected, $status, $path . ': ' . substr($body, 0, 150));
                self::assertStringContainsString('error-card', $body, $path);
                self::assertStringContainsString('public/css/404/404.css', $body, $path);
            }
            [$status, $body] = $this->request($base . '/ajax/.env', 'POST', 'error_code=500');
            self::assertSame(200, $status);
            self::assertSame('forbidden', json_decode($body, true)['code'] ?? null);
            [$status, $body] = $this->request($base . '/api/.env', 'POST');
            self::assertSame(403, $status);
            self::assertSame('forbidden', json_decode($body, true)['code'] ?? null);
            [$status, $body] = $this->request($base . '/sse/.env');
            self::assertSame(200, $status);
            self::assertStringContainsString('event: error', $body);
            self::assertStringNotContainsString('<html', $body);
            [$status, $body] = $this->request($base . '/ajax/lostpassword', 'POST', 'recovery_email=unknown%40example.test&middle_name=');
            self::assertSame(200, $status);
            self::assertSame('recovery_requested', json_decode($body, true)['code'] ?? null);
        } finally {
            foreach (array_reverse($processes) as $process) { proc_terminate($process); proc_close($process); }
            $this->removeDirectory($root);
        }
    }

    public function testPublicFilePolicyIsExplicitInBothServers(): void
    {
        $root = dirname(__DIR__) . '/resources/skeleton/';
        $apache = (string)file_get_contents($root . '.htaccess');
        $nginx = (string)file_get_contents($root . 'nginx.conf');
        self::assertStringContainsString('RewriteRule ^public/ - [END]', $apache);
        foreach ([$apache, $nginx] as $config) self::assertStringContainsString('core|deployment|packages|storage', $config);
        self::assertStringContainsString('RewriteRule ^(?:uploads|downloads|download)(?:/|$) - [F,NC]', $apache);
        self::assertStringContainsString('RewriteCond %{REQUEST_FILENAME} -f', $apache);
        self::assertStringNotContainsString('RewriteCond %{REQUEST_FILENAME} !-f', $apache);
        foreach ([$apache, $nginx] as $config) self::assertStringContainsString('uploads/(?:library|(?:user|tenant)/[1-9][0-9]*/library)/', $config);
        self::assertStringContainsString('location /uploads/ { return 403; }', $nginx);
        self::assertStringContainsString('rewrite ^ /index.php last;', $nginx);
        self::assertStringNotContainsString('try_files $uri /index.php', $nginx);
    }

    public function testRealApacheAllowsVirtualAppRoutesWithoutExposingInternalFiles(): void
    {
        $apache = (string)getenv('GFRAME_TEST_APACHE');
        $phpModule = (string)getenv('GFRAME_TEST_APACHE_PHP_MODULE');
        if (PHP_OS_FAMILY !== 'Windows' || !is_file($apache) || !is_file($phpModule)) {
            self::markTestSkipped('Requiere Apache y su módulo PHP de pruebas en Windows.');
        }
        $root = sys_get_temp_dir() . '/gframe-apache-test-' . bin2hex(random_bytes(6));
        mkdir($root . '/project', 0775, true);
        $root = str_replace('\\', '/', (string)realpath($root));
        $project = $root . '/project';
        $port = $this->freePort();
        $process = null;
        try {
            $result = ProjectInstaller::frameworkDefault()->install(['project_root' => $project, 'profile' => 'managed', 'app_name' => 'Apache test', 'app_url' => 'http://127.0.0.1:' . $port, 'database' => ['driver' => 'sqlite', 'path' => 'storage/database.sqlite'], 'superadministrator' => ['email' => 'test@example.test', 'password' => 'Password-123']]);
            self::assertSame('success', $result['status']);
            mkdir($project . '/packages');
            file_put_contents($project . '/packages/autoload.php', '<?php require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';');
            $this->addVirtualAppRoute($project);
            foreach (['app/private.json', 'app/private.txt', 'app/internal.php', 'public/probe.txt'] as $file) {
                file_put_contents($project . '/' . $file, 'internal-file-marker');
            }
            $serverRoot = str_replace('\\', '/', dirname($apache, 2));
            $phpRoot = str_replace('\\', '/', dirname($phpModule));
            $config = 'ServerRoot "' . $serverRoot . '"' . "\n";
            $config .= 'Listen 127.0.0.1:' . $port . "\nServerName 127.0.0.1\n";
            foreach (['authz_core', 'dir', 'mime', 'rewrite', 'log_config'] as $module) {
                $config .= 'LoadModule ' . $module . '_module modules/mod_' . $module . '.so' . "\n";
            }
            foreach (['libcrypto-3-x64.dll', 'libssl-3-x64.dll', 'libssh2.dll', 'nghttp2.dll', 'brotlicommon.dll', 'brotlidec.dll', 'php8ts.dll', 'libpq.dll', 'libsqlite3.dll'] as $library) {
                if (is_file($phpRoot . '/' . $library)) $config .= 'LoadFile "' . $phpRoot . '/' . $library . '"' . "\n";
            }
            $config .= 'LoadModule php_module "' . str_replace('\\', '/', $phpModule) . '"' . "\n";
            $config .= 'PHPIniDir "' . $phpRoot . '"' . "\n";
            $config .= 'PidFile "' . $root . '/httpd.pid"' . "\n";
            $config .= 'ErrorLog "' . $root . '/error.log"' . "\n";
            $config .= 'DocumentRoot "' . $project . '"' . "\n";
            $config .= '<Directory "' . $project . '">' . "\nAllowOverride All\nRequire all granted\n</Directory>\n";
            $config .= "<FilesMatch \"\\.php$\">\nSetHandler application/x-httpd-php\n</FilesMatch>\n";
            file_put_contents($root . '/httpd.conf', $config);
            $process = proc_open([$apache, '-f', $root . '/httpd.conf', '-X'], [0 => ['pipe', 'r'], 1 => ['file', $root . '/process.log', 'a'], 2 => ['file', $root . '/process.log', 'a']], $pipes, $serverRoot);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $this->waitPort($port, $root . '/process.log');
            $base = 'http://127.0.0.1:' . $port;
            [$status, $body] = $this->request($base . '/app/recordatorios');
            self::assertSame(200, $status, substr($body, 0, 200));
            self::assertStringContainsString('Algo maravilloso se construye aquí.', $body);
            $this->assertPublicWorkerDoesNotStartSession($base);
            foreach (['app/private.json', 'app/private.txt', 'app/internal.php', 'app/internal.php/extra', 'app/controllers/home/HomeController.php', 'config/app.php', '.env'] as $file) {
                [$status, $body] = $this->request($base . '/' . $file);
                self::assertSame(403, $status, $file);
                self::assertStringNotContainsString('internal-file-marker', $body);
            }
            self::assertSame(404, $this->request($base . '/app/missing')[0]);
            self::assertSame('internal-file-marker', $this->request($base . '/public/probe.txt')[1]);
        } finally {
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            $this->removeDirectory($root);
        }
    }

    private function addVirtualAppRoute(string $project): void
    {
        file_put_contents($project . '/config/routes/routes_web.php', "\nRouteBuilder::get('app/recordatorios', 'home/HomeController@index')->template('home')->view('homeIndex')->registerFinal();\n", FILE_APPEND);
        file_put_contents($project . '/app/controllers/home/PublicWorkerController.php', '<?php final class PublicWorkerController { public function index() { header("Content-Type: application/javascript"); echo "/* public-worker-session-" . session_status() . " */"; exit; } }');
        file_put_contents($project . '/config/routes/routes_web.php', "\nRouteBuilder::get('app/public-worker.js', 'home/PublicWorkerController@index')->noSession()->registerFinal();\n", FILE_APPEND);
    }

    private function assertPublicWorkerDoesNotStartSession(string $base): void
    {
        [$status, $body, $headers] = $this->request($base . '/app/public-worker.js', 'GET', '', ['Cookie: PHPSESSID=obsolete-session']);
        self::assertSame(200, $status);
        self::assertSame('/* public-worker-session-' . PHP_SESSION_NONE . ' */', $body);
        self::assertSame([], array_values(array_filter($headers, static fn(string $header): bool => stripos($header, 'Set-Cookie:') === 0)));
        [$status, , $headers] = $this->request($base . '/es/app/public-worker.js', 'GET', '', ['Cookie: PHPSESSID=obsolete-session']);
        self::assertSame(301, $status);
        self::assertSame([], array_values(array_filter($headers, static fn(string $header): bool => stripos($header, 'Set-Cookie:') === 0)));
        [$status, , $headers] = $this->request($base . '/login');
        self::assertSame(200, $status);
        self::assertNotEmpty(array_filter($headers, static fn(string $header): bool => stripos($header, 'Set-Cookie:') === 0));
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int)substr($address, strrpos($address, ':') + 1);
    }

    private function waitPort(int $port, string $log): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $error, $message, 0.1);
            if (is_resource($socket)) { fclose($socket); return; }
            usleep(100000);
        }
        self::fail('El servidor de prueba no inició en el puerto ' . $port . ': ' . (is_file($log) ? file_get_contents($log) : 'sin log'));
    }

    private function request(string $url, string $method = 'GET', string $content = '', array $headers = []): array
    {
        if ($method === 'POST') $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10, 'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $content]]);
        $body = (string)file_get_contents($url, false, $context);
        preg_match('/^HTTP\/\S+\s+(\d+)/', $http_response_header[0] ?? '', $match);
        return [(int)($match[1] ?? 0), $body, $http_response_header];
    }

    private function removeDirectory(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $child = $path . '/' . $item;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }
        rmdir($path);
    }
}
