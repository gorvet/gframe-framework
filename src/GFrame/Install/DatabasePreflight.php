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
