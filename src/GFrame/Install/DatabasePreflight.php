<?php

namespace GFrame\Install;

use PDO;
use PDOException;

/** Comprueba la conexión y el destino sin crear bases, tablas ni archivos. */
final class DatabasePreflight
{
    public function check(string $projectRoot, array $settings): array
    {
        if (($settings['driver'] ?? 'mysql') === 'sqlite') {
            $path = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database.sqlite';
            if (!is_file($path)) return $this->success('database_missing', 'La base de datos se creará al instalar.');
            try {
                $pdo = new PDO('sqlite:file:' . str_replace('\\', '/', $path) . '?mode=ro');
                return $this->emptyDatabase($pdo, 'sqlite');
            } catch (PDOException $exception) {
                return $this->error('database_connection_failed', 'No se puede abrir la base SQLite. Comprueba sus permisos.');
            }
        }
        return $this->boundedMysqlCheck($settings);
    }

    /** El proceso hijo contiene DNS, conexión, autenticación y consulta en un solo plazo. */
    private function boundedMysqlCheck(array $settings): array
    {
        if (!function_exists('proc_open')) return $this->error('database_preflight_unavailable', 'La comprobación requiere PHP CLI y proc_open.');
        $binary = (string)(getenv('GFRAME_PHP_BINARY') ?: (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server' ? PHP_BINARY : PHP_BINDIR . '/php' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '')));
        if (!is_file($binary)) return $this->error('database_preflight_unavailable', 'Configura GFRAME_PHP_BINARY con PHP CLI.');
        $deadline = microtime(true) + 5;
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = @proc_open([$binary, dirname(__DIR__, 3) . '/bin/database-preflight.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $null, 'a']], $pipes, null, null, ['bypass_shell' => true, 'create_process_group' => true]);
        if (!is_resource($process)) return $this->error('database_preflight_unavailable', 'No se pudo iniciar PHP CLI para la comprobación.');
        $output = '';
        try {
            $input = json_encode($settings, JSON_THROW_ON_ERROR);
            if (strlen($input) > 8192 || fwrite($pipes[0], $input) !== strlen($input)) return $this->error('invalid_database_settings', 'La configuración de base de datos no es válida.');
            fclose($pipes[0]); unset($pipes[0]);
            do {
                $status = proc_get_status($process);
                if (!$status['running']) break;
                if (microtime(true) >= $deadline) {
                    return $this->error('database_connection_timeout', 'La comprobación superó el plazo de cinco segundos.');
                }
                usleep(10000);
            } while (true);
            $output = stream_get_contents($pipes[1], 16384);
            $result = json_decode($output, true);
            return is_array($result) && isset($result['status'], $result['code'], $result['message'])
                ? $result : $this->error('database_preflight_unavailable', 'PHP CLI no pudo completar la comprobación. Comprueba sus extensiones PDO.');
        } finally {
            if (proc_get_status($process)['running']) proc_terminate($process, 9);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
    }

    /** Entrada exclusiva del ejecutable interno; no carga configuración de la aplicación. */
    public static function runWorker(): void
    {
        if (PHP_SAPI !== 'cli') return;
        $settings = json_decode((string)stream_get_contents(STDIN, 8193), true);
        if (!is_array($settings)) exit(1);
        echo json_encode((new self())->checkMysql($settings), JSON_THROW_ON_ERROR);
    }

    private function checkMysql(array $settings): array
    {
        $name = trim((string)($settings['database'] ?? ''));
        $host = trim((string)($settings['host'] ?? 'localhost'));
        if ($name === '' || $host === '' || str_contains($host, ';') || str_contains($name, ';')) {
            return $this->error('invalid_database_settings', 'Indica un servidor y un nombre de base de datos válidos.');
        }
        $dsn = 'mysql:host=' . $host . ';port=' . (int)($settings['port'] ?? 3306) . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn . ';dbname=' . $name, (string)($settings['username'] ?? ''), (string)($settings['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            return $this->emptyDatabase($pdo, 'mysql');
        } catch (PDOException $exception) {
            $code = (int)($exception->errorInfo[1] ?? 0);
            if ($code === 1049) {
                try {
                    new PDO($dsn, (string)($settings['username'] ?? ''), (string)($settings['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
                    return $this->success('database_missing', 'Conexión correcta. La base de datos se creará al instalar si el usuario tiene permisos.');
                } catch (PDOException $ignored) {
                    return $this->error('database_connection_failed', 'No se pudo conectar con el servidor de base de datos.');
                }
            }
            return $this->error($code === 1045 || $code === 1044 ? 'database_access_denied' : 'database_connection_failed',
                $code === 1045 || $code === 1044 ? 'El usuario no tiene acceso. Comprueba el usuario, la contraseña y los permisos de la base de datos.' : 'No se pudo conectar. Comprueba el servidor, el puerto y que MySQL esté iniciado.');
        }
    }

    public function emptyDatabase(PDO $pdo, string $driver): array
    {
        $tables = $pdo->query($driver === 'sqlite' ? "SELECT name FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' LIMIT 1" : 'SHOW TABLES')->fetchColumn();
        return $tables === false
            ? $this->success('database_empty', 'Conexión correcta. La base de datos está vacía.')
            : $this->error('database_not_empty', 'La base de datos ya contiene tablas. Usa una base vacía para no modificar datos existentes.');
    }

    private function success(string $code, string $message): array { return ['status' => 'success', 'code' => $code, 'message' => $message]; }
    private function error(string $code, string $message): array { return ['status' => 'error', 'code' => $code, 'message' => $message]; }
}
