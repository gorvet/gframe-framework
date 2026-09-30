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
            $input = [
                'project_root' => $project,
                'profile' => $profile,
                'app_name' => 'Prueba ' . $profile,
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

            $socket = stream_socket_server('tcp://127.0.0.1:0');
            self::assertIsResource($socket);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $port = (int)substr((string)$address, strrpos((string)$address, ':') + 1);
            $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project, $router];
            $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $nullDevice, 'a'], 2 => ['file', $nullDevice, 'a']], $pipes, $project);
            self::assertIsResource($process);
            fclose($pipes[0]);
            try {
                $base = 'http://127.0.0.1:' . $port;
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
                    self::assertStringContainsString('La base está preparada', $body, $profile);
                }

                if ($profile !== 'static') {
                    [$loginStatus] = $this->request($base . '/login');
                    self::assertSame(200, $loginStatus, $profile . ': login');
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

                [$installerStatus, $installerBody] = $this->request($base . '/public/install/');
                self::assertSame(200, $installerStatus, $profile . ': instalador bloqueado');
                self::assertStringContainsString('La aplicación quedó instalada correctamente', $installerBody);
                self::assertStringNotContainsString('id="installer-form"', $installerBody);
            } finally {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }

    private function request(string $url): array
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        preg_match('/^HTTP\/\S+\s+(\d+)/', (string)($headers[0] ?? ''), $matches);
        return [(int)($matches[1] ?? 0), (string)$body];
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
