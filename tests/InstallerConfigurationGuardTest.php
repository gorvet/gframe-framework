<?php

namespace GFrame\Tests;

use GFrame\Install\ProjectScaffolder;
use PHPUnit\Framework\TestCase;

final class InstallerConfigurationGuardTest extends TestCase
{
    public function testConfiguredInstallerRejectsGetAndPreflightBeforePublishingAssets(): void
    {
        $root = sys_get_temp_dir() . '/gframe-install-guard-' . bin2hex(random_bytes(6));
        mkdir($root . '/sessions', 0775, true);
        ProjectScaffolder::frameworkDefault()->publish($root);
        mkdir($root . '/packages', 0775, true);
        file_put_contents($root . '/packages/autoload.php', '<?php require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $sessionPath = str_replace('\\', '/', $root . '/sessions');
        $server = proc_open([PHP_BINARY, '-d', 'session.save_path="' . $sessionPath . '"', '-S', $address, '-t', $root],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'a'], 2 => ['file', $null, 'a']], $pipes);
        self::assertIsResource($server);
        fclose($pipes[0]);
        try {
            $ready = false;
            for ($i = 0; $i < 40; $i++) {
                $connection = @stream_socket_client('tcp://' . $address, $number, $message, 0.1);
                if ($connection) { fclose($connection); $ready = true; break; }
                usleep(100000);
            }
            self::assertTrue($ready);
            foreach (['config/app.php', '.env', 'config/bootstrap.php', 'core/Config.php'] as $file) {
                $path = $root . '/' . $file;
                file_put_contents($path, 'guard-canary');
                foreach (['GET', 'POST'] as $method) {
                    $body = file_get_contents('http://' . $address . '/install.php', false, stream_context_create(['http' => [
                        'method' => $method, 'ignore_errors' => true, 'timeout' => 5,
                        'header' => 'Content-Type: application/x-www-form-urlencoded',
                        'content' => $method === 'POST' ? 'installer_action=check_database&database_host=127.0.0.1&database_port=1' : '',
                    ]]));
                    self::assertStringContainsString('403', $http_response_header[0]);
                    self::assertStringContainsString('El instalador', $body);
                    self::assertSame('guard-canary', file_get_contents($path));
                    self::assertFileDoesNotExist($root . '/storage/gframe-installed.json');
                    self::assertFileDoesNotExist($root . '/public/vendors/external/sweetalert2/sweetalert2.all.min.js');
                }
                unlink($path);
            }
        } finally {
            proc_terminate($server);
            proc_close($server);
            $this->removeDirectory($root);
        }
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $directory . '/' . $entry;
            if (is_dir($path)) $this->removeDirectory($path);
            else unlink($path);
        }
        rmdir($directory);
    }
}
