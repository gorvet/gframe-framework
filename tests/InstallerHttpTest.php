<?php

namespace GFrame\Tests;

use GFrame\Install\ProjectInstaller;
use PDO;
use PHPUnit\Framework\TestCase;

final class InstallerHttpTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        $this->temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-http-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryPath);
    }

    public function testEachProfileServesItsInitialPageAndPublishedAssetsOverHttp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite no está disponible.');
        }
        if (!function_exists('proc_open')) {
            self::markTestSkipped('No se puede iniciar el servidor HTTP de prueba.');
        }

        foreach (['static', 'managed', 'intranet', 'saas'] as $profile) {
            $project = $this->temporaryPath . DIRECTORY_SEPARATOR . $profile;
            mkdir($project, 0775, true);
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            self::assertIsResource($socket);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $port = (int)substr((string)$address, strrpos((string)$address, ':') + 1);
            $base = 'http://127.0.0.1:' . $port;
            $input = [
                'project_root' => $project,
                'profile' => $profile,
                'app_name' => 'Prueba ' . $profile,
                'app_url' => $base,
            ];
            if ($profile !== 'static') {
                $input['database'] = ['driver' => 'sqlite', 'path' => 'storage/database.sqlite'];
                $input['superadministrator'] = ['email' => $profile . '@example.test', 'password' => 'Password-123'];
            } else {
                $input['seo_enabled'] = true;
                $input['seo_allow_indexing'] = false;
            }
            $result = ProjectInstaller::frameworkDefault()->install($input);
            self::assertSame('success', $result['status'], $profile);

            $packages = $project . DIRECTORY_SEPARATOR . 'packages';
            mkdir($packages);
            $frameworkAutoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'autoload.php';
            file_put_contents($packages . DIRECTORY_SEPARATOR . 'autoload.php', '<?php require ' . var_export($frameworkAutoload, true) . ';');
            $router = $project . DIRECTORY_SEPARATOR . 'test-router.php';
            file_put_contents($router, <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($path) && (is_file(__DIR__ . $path) || is_file(rtrim(__DIR__ . $path, '/\\') . '/index.php'))) return false;
require __DIR__ . '/index.php';
PHP);

            $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project, $router];
            $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $nullDevice, 'a'], 2 => ['file', $nullDevice, 'a']], $pipes, $project);
            self::assertIsResource($process);
            fclose($pipes[0]);
            try {
                $ready = false;
                for ($attempt = 0; $attempt < 40; $attempt++) {
                    $connection = @stream_socket_client('tcp://127.0.0.1:' . $port, $error, $message, 0.1);
                    if (is_resource($connection)) {
                        fclose($connection);
                        $ready = true;
                        break;
                    }
                    usleep(100000);
                }
                self::assertTrue($ready, $profile . ': servidor HTTP no inició');

                [$status, $body] = $this->request($base . '/');
                self::assertSame($profile === 'intranet' ? 301 : 200, $status, $profile . ': ' . substr($body, 0, 250));
                if ($profile !== 'intranet') {
                    self::assertStringContainsString('Algo maravilloso se construye aquí.', $body, $profile);
                }

                if ($profile !== 'static') {
                    [$loginStatus, $loginBody, $loginHeaders] = $this->request($base . '/login');
                    self::assertSame(200, $loginStatus, $profile . ': login');
                    self::assertStringContainsString('id="login"', $loginBody);
                    self::assertStringContainsString('/login/lostpassword', $loginBody);
                    self::assertStringContainsString('Crear una cuenta', $loginBody);
                    self::assertStringContainsString('class="auth-page"', $loginBody);
                    self::assertStringContainsString('gframe-footer', $loginBody);
                    self::assertStringContainsString('public/img/logo.png', $loginBody);
                    self::assertStringContainsString('id="toastBox"', $loginBody);
                    $headEnd = strpos($loginBody, '</head>');
                    $siteUrlDeclaration = strpos($loginBody, 'window.site_url =');
                    $jqueryScript = strpos($loginBody, 'public/vendors/external/jquery/jquery.min.js');
                    self::assertNotFalse($siteUrlDeclaration);
                    self::assertGreaterThan($headEnd, $siteUrlDeclaration, 'site_url se declara en el footer');
                    self::assertLessThan($jqueryScript, $siteUrlDeclaration, 'site_url está disponible antes del JS');
                    foreach ([
                        '/login' => 'AuthLogin.js',
                        '/login/register' => 'AuthRegister.js',
                        '/login/lostpassword' => 'AuthLostpassword.js',
                        '/login/resetpassword?rp=prueba' => 'AuthResetpassword.js',
                    ] as $path => $script) {
                        [$pageStatus, $page] = $this->request($base . $path);
                        self::assertSame(200, $pageStatus, $profile . ': ' . $path);
                        self::assertStringContainsString('public/js/modules/auth/' . $script, $page);
                        self::assertStringNotContainsString('public/js/modules/auth/auth.js', $page);
                        foreach (['public/js/modules/auth/' . $script, 'public/vendors/external/sweetalert2/sweetalert2.all.min.js', 'public/vendors/external/sweetalert2/sweetalert2.min.css', 'public/vendors/external/sweetalert2/sweetTheme.css', 'public/js/core/heartbeat.js', 'public/js/core/session.js'] as $asset) {
                            self::assertStringContainsString($asset, $page);
                            self::assertSame(200, $this->request($base . '/' . $asset)[0], $profile . ': ' . $asset);
                        }
                    }
                    foreach ([
                        '/ajax/lostpassword' => ['recovery_email' => 'unknown@example.test'],
                        '/ajax/resetpassword' => ['rpuser_token' => 'prueba', 'reset_password' => 'Password-123'],
                        '/ajax/verifyacount' => ['login_email' => 'unknown@example.test'],
                        '/ajax/validateacount' => ['vtoken' => 'prueba'],
                    ] as $path => $fields) {
                        [$endpointStatus, $endpointBody] = $this->request($base . $path, 'POST', http_build_query($fields + ['middle_name' => '']));
                        self::assertSame(200, $endpointStatus, $profile . ': ' . $path . ': ' . substr($endpointBody, 0, 200));
                        self::assertIsArray(json_decode($endpointBody, true), $profile . ': ' . $path . ': ' . substr($endpointBody, 0, 1000));
                        self::assertArrayHasKey('code', json_decode($endpointBody, true));
                        if (in_array($path, ['/ajax/resetpassword', '/ajax/validateacount'], true)) {
                            self::assertSame('invalid_token', json_decode($endpointBody, true)['code']);
                        }
                    }
                    self::assertSame(200, $this->request($base . '/login/lostpassword')[0], $profile . ': recuperación');
                    self::assertSame(200, $this->request($base . '/login/resetpassword?rp=prueba')[0], $profile . ': restablecimiento');

                    $cookie = '';
                    foreach ($loginHeaders as $header) {
                        if (stripos($header, 'Set-Cookie: ') === 0) {
                            $cookie = explode(';', substr($header, 12), 2)[0];
                            break;
                        }
                    }
                    self::assertNotSame('', $cookie, $profile . ': cookie de sesión');
                    [$authStatus, $authBody, $authHeaders] = $this->request($base . '/ajax/login', 'POST', http_build_query([
                        'login_email' => $profile . '@example.test',
                        'login_password' => 'Password-123',
                        'middle_name' => '',
                    ]), $cookie);
                    self::assertSame(200, $authStatus, $profile . ': autenticación ' . substr($authBody, 0, 200));
                    $auth = json_decode($authBody, true);
                    self::assertSame('success', $auth['status'] ?? null, $profile . ': autenticación');
                    self::assertSame('admin', $auth['redirect'] ?? null, $profile . ': destino administrativo: ' . $authBody);
                    foreach ($authHeaders as $header) {
                        if (stripos($header, 'Set-Cookie: ') === 0) {
                            $cookie = explode(';', substr($header, 12), 2)[0];
                            break;
                        }
                    }
                    [$repeatStatus, $repeatBody] = $this->request($base . '/ajax/login', 'POST', http_build_query(['middle_name' => '']), $cookie);
                    self::assertSame(200, $repeatStatus);
                    self::assertSame('already_logged', json_decode($repeatBody, true)['code'] ?? null, $repeatBody);
                    [$adminStatus, $adminBody] = $this->request($base . '/admin', 'GET', null, $cookie);
                    self::assertSame(200, $adminStatus, $profile . ': panel');
                    self::assertStringContainsString('Escritorio', $adminBody, $profile . ': panel');
                    self::assertSame(1, substr_count($adminBody, 'src="' . $base . '/public/js/core/heartbeat.js'), $profile . ': heartbeat único');
                    self::assertSame(1, substr_count($adminBody, 'src="' . $base . '/public/js/core/session.js'), $profile . ': sesión única');
                }

                [$robotsStatus, $robotsBody] = $this->request($base . '/robots.txt');
                if ($profile === 'intranet') {
                    self::assertSame(404, $robotsStatus, $profile . ': robots.txt');
                } else {
                    self::assertSame(200, $robotsStatus, $profile . ': robots.txt');
                    self::assertStringContainsString('User-agent:', $robotsBody, $profile);
                    if ($profile === 'static') self::assertStringContainsString('Disallow: /', $robotsBody);
                }

                [$assetStatus] = $this->request($base . '/public/vendors/external/bootstrap/css/bootstrap.min.css');
                self::assertSame(200, $assetStatus, $profile . ': Bootstrap');
                foreach (['variables.css', 'bootstrap-buttons-compat.css', 'common.css', 'colores.html'] as $asset) {
                    self::assertSame(200, $this->request($base . '/public/css/' . $asset)[0], $profile . ': ' . $asset);
                }
                $pageBody = $profile === 'intranet' ? $loginBody : $body;
                self::assertStringContainsString('public/css/variables.css', $pageBody, $profile . ': variables cargadas');
                self::assertStringContainsString('public/css/common.css', $pageBody, $profile . ': estilos comunes cargados');
                self::assertSame(200, $this->request($base . '/public/img/logo.png')[0], $profile . ': logotipo');
                self::assertSame(200, $this->request($base . '/public/img/favicon.png')[0], $profile . ': favicon');

                [$installerStatus, $installerBody, $installerHeaders] = $this->request($base . '/install.php', 'GET', null, '', false);
                self::assertSame(303, $installerStatus, $profile . ': instalador bloqueado');
                self::assertContains('Location: /', $installerHeaders);
                self::assertStringNotContainsString('id="installer-form"', $installerBody);
            } finally {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }

    private function request(string $url, string $method = 'GET', ?string $content = null, string $cookie = '', bool $follow = true): array
    {
        $headers = ['X-Requested-With: XMLHttpRequest'];
        if ($content !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
        $context = stream_context_create(['http' => [
            'ignore_errors' => true,
            'follow_location' => $follow ? 1 : 0,
            'timeout' => 5,
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $content ?? '',
        ]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        preg_match('/^HTTP\/\S+\s+(\d+)/', (string)($headers[0] ?? ''), $matches);
        return [(int)($matches[1] ?? 0), (string)$body, $headers];
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->removeDirectory($target) : unlink($target);
        }
        rmdir($path);
    }
}
