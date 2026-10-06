<?php

namespace GFrame\Tests;

use GFrame\Install\ProjectScaffolder;
use PHPUnit\Framework\TestCase;

final class InstallerWizardHttpTest extends TestCase
{
    public function testWizardInstallsEveryProfileAndRejectsInvalidSelections(): void
    {
        foreach (['static', 'managed', 'intranet', 'saas'] as $profile) {
            $root = sys_get_temp_dir() . '/gframe-wizard-http-' . bin2hex(random_bytes(6));
            mkdir($root);
            ProjectScaffolder::frameworkDefault()->publish($root);
            mkdir($root . '/packages');
            file_put_contents($root . '/packages/autoload.php', '<?php require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';');
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $url = 'http://' . $address . '/install.php';
            $router = $root . '/test-router.php';
            file_put_contents($router, <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file(__DIR__ . $path)) return false;
$_SERVER['SCRIPT_NAME'] = str_starts_with($path, '/nested-project/') ? '/nested-project/index.php' : '/index.php';
require __DIR__ . '/index.php';
PHP);
            $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $server = proc_open([PHP_BINARY, '-S', $address, '-t', $root, $router], [0 => ['pipe', 'r'], 1 => ['file', $null, 'a'], 2 => ['file', $null, 'a']], $pipes);
            self::assertIsResource($server);
            fclose($pipes[0]);
            try {
                for ($attempt = 0; $attempt < 40; $attempt++) {
                    $connection = @stream_socket_client('tcp://' . $address, $number, $message, 0.1);
                    if ($connection) { fclose($connection); break; }
                    usleep(100000);
                }
                foreach (['/', '/login', '/admin/users', '/missing', '/nested-project/login'] as $path) {
                    file_get_contents('http://' . $address . $path, false, stream_context_create(['http' => ['follow_location' => 0]]));
                    self::assertStringContainsString('302', $http_response_header[0]);
                    $location = str_starts_with($path, '/nested-project/') ? '/nested-project/install.php' : '/install.php';
                    self::assertContains('Location: ' . $location, $http_response_header, $path . ': ' . implode(' | ', $http_response_header));
                }
                $html = file_get_contents($url);
                self::assertIsString($html);
                self::assertStringNotContainsString('data-step="Bienvenida"', $html);
                self::assertStringNotContainsString('password_confirmation', $html);
                self::assertStringContainsString('data-step="Módulos"', $html);
                self::assertStringContainsString('id="installer-account"', $html);
                self::assertFileExists($root . '/public/vendors/external/sweetalert2/sweetalert2.all.min.js');
                self::assertFileDoesNotExist($root . '/storage/gframe-installed.json');
                self::assertFileDoesNotExist($root . '/config/app.php');
                preg_match('/name="csrf" value="([^"]+)"/', $html, $csrf);
                preg_match('/Set-Cookie: (PHPSESSID=[^;]+)/i', implode("\n", $http_response_header), $cookie);
                $input = ['csrf' => $csrf[1], 'profile' => $profile, 'app_name' => 'Prueba ' . $profile, 'database_driver' => 'sqlite'];
                if ($profile !== 'static') $input += ['email' => $profile . '@example.test', 'password' => 'Password-123'];
                $request = static function (array $data) use ($url, $cookie): string {
                    return file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . $cookie[1], 'content' => http_build_query($data), 'ignore_errors' => true]]));
                };
                if ($profile === 'static') {
                    $rejected = $request($input + ['modules' => ['rich-text-editor']]);
                    self::assertStringContainsString('no corresponden al tipo de proyecto', $rejected);
                    self::assertFileDoesNotExist($root . '/storage/gframe-installed.json');
                } else {
                    $check = json_decode($request($input + ['installer_action' => 'check_database']), true);
                    self::assertSame('database_missing', $check['code']);
                    self::assertFileDoesNotExist($root . '/storage/database.sqlite');
                    $badConnection = array_replace($input, ['installer_action' => 'check_database', 'database_driver' => 'mysql', 'database_host' => '127.0.0.1', 'database_port' => 1, 'database_name' => 'gframe_check', 'database_user' => 'invalid']);
                    $error = json_decode($request($badConnection), true);
                    self::assertSame('database_connection_failed', $error['code']);
                    self::assertSame('error', $error['status']);
                }
                $completed = $request($input);
                self::assertStringContainsString('La aplicación quedó instalada correctamente.', $completed);
                self::assertStringContainsString('El instalador ha quedado bloqueado automáticamente.', $completed);
                self::assertStringNotContainsString('Bloquea el acceso', $completed);
                $lockHash = hash_file('sha256', $root . '/storage/gframe-installed.json');
                foreach (['GET', 'POST'] as $method) {
                    $blocked = file_get_contents($url, false, stream_context_create(['http' => ['method' => $method, 'follow_location' => 0]]));
                    self::assertStringContainsString('303', $http_response_header[0]);
                    self::assertContains('Location: /', $http_response_header);
                    self::assertSame('', $blocked);
                    self::assertSame($lockHash, hash_file('sha256', $root . '/storage/gframe-installed.json'));
                }
                $lock = json_decode(file_get_contents($root . '/storage/gframe-installed.json'), true);
                self::assertSame($profile, $lock['profile']);
                self::assertContains('gf-select', $lock['modules']);
                self::assertContains('gf-table', $lock['modules']);
                self::assertNotContains('rich-text-editor', $lock['modules']);
                if ($profile === 'static') {
                    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/gframe-update', '--project=' . $root, '--dry-run'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $updatePipes);
                    fclose($updatePipes[0]);
                    $output = stream_get_contents($updatePipes[1]);
                    $error = stream_get_contents($updatePipes[2]);
                    fclose($updatePipes[1]);
                    fclose($updatePipes[2]);
                    self::assertSame(0, proc_close($process), $error);
                    self::assertStringContainsString('Vista previa completada.', $output);
                }
            } finally {
                proc_terminate($server);
                proc_close($server);
                $this->removeTemporaryDirectory($root);
            }
        }
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $directory . '/' . $entry;
            if (is_dir($path)) $this->removeTemporaryDirectory($path);
            else unlink($path);
        }
        rmdir($directory);
    }
}
