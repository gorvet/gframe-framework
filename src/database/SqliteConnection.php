<?php

final class SqliteConnection implements DatabaseConnectionInterface
{
    /**
     * @return PDO|array{status:string,message:string,code:int|string}
     */
    public function connect(array $config = [])
    {
        $sqlitePath = isset($config['path'])
            ? (string)$config['path']
            : (defined('DB_SQLITE_PATH') ? (string)DB_SQLITE_PATH : (ABSPATH . 'app/database/database.sqlite'));
        $enableForeignKeys = isset($config['foreign_keys'])
            ? (bool)$config['foreign_keys']
            : (defined('DB_SQLITE_FOREIGN_KEYS') ? (bool)DB_SQLITE_FOREIGN_KEYS : true);
        $busyTimeoutMs = isset($config['busy_timeout_ms']) ? max(0, (int)$config['busy_timeout_ms']) : 5000;

        try {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $pdo = new PDO(
                'sqlite:' . $sqlitePath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            if ($enableForeignKeys) {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
            if ($busyTimeoutMs > 0) {
                $pdo->exec('PRAGMA busy_timeout = ' . $busyTimeoutMs);
            }

            return $pdo;
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }
}
