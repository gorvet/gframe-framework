<?php

final class MySqlConnection implements DatabaseConnectionInterface
{
    /**
     * @return PDO|array{status:string,message:string,code:int|string}
     */
    public function connect(array $config = [])
    {
        $host = isset($config['host']) ? (string)$config['host'] : (defined('DB_HOST') ? (string)DB_HOST : 'localhost');
        $dbName = isset($config['database']) ? (string)$config['database'] : (defined('DB_NAME') ? (string)DB_NAME : '');
        $user = isset($config['username']) ? (string)$config['username'] : (defined('DB_USER') ? (string)DB_USER : '');
        $pass = isset($config['password']) ? (string)$config['password'] : (defined('DB_PASSWORD') ? (string)DB_PASSWORD : '');
        $charset = isset($config['charset']) ? (string)$config['charset'] : (defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4');
        $port = isset($config['port']) ? (int)$config['port'] : null;
        $collation = isset($config['collation']) ? (string)$config['collation'] : 'utf8mb4_unicode_ci';
        $autoCreate = isset($config['auto_create'])
            ? (bool)$config['auto_create']
            : (defined('DB_AUTO_CREATE') ? (bool)DB_AUTO_CREATE : false);

        $dsn = "mysql:host={$host}";
        if ($port !== null && $port > 0) {
            $dsn .= ";port={$port}";
        }
        $dsn .= ";dbname={$dbName};charset={$charset}";

        $serverDsn = "mysql:host={$host}";
        if ($port !== null && $port > 0) {
            $serverDsn .= ";port={$port}";
        }
        $serverDsn .= ";charset={$charset}";

        try {
            return new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset};SET time_zone = '+00:00';",
                ]
            );
        } catch (PDOException $e) {
            if ($autoCreate && $dbName !== '' && $this->isUnknownDatabaseError($e)) {
                try {
                    $serverPdo = new PDO(
                        $serverDsn,
                        $user,
                        $pass,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset};SET time_zone = '+00:00';",
                        ]
                    );
                    $safeDbName = str_replace('`', '``', $dbName);
                    $safeCollation = preg_replace('/[^a-z0-9_]/i', '', $collation);
                    if ($safeCollation === '' || !preg_match('/^[a-z0-9_]+$/i', $safeCollation)) {
                        $safeCollation = 'utf8mb4_unicode_ci';
                    }

                    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET {$charset} COLLATE {$safeCollation}");

                    return new PDO(
                        $dsn,
                        $user,
                        $pass,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset};SET time_zone = '+00:00';",
                        ]
                    );
                } catch (PDOException $bootstrapException) {
                    return [
                        'status' => 'error',
                        'message' => $bootstrapException->getMessage(),
                        'code' => $bootstrapException->getCode(),
                    ];
                }
            }

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    private function isUnknownDatabaseError(PDOException $e): bool
    {
        $message = strtolower((string)$e->getMessage());
        return strpos($message, 'unknown database') !== false
            || strpos($message, '1049') !== false;
    }
}
