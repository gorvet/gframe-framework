<?php

final class DatabaseManager
{
    /** @var array<string, PDO|array{status:string,message:string,code:int|string}> */
    private static array $pool = [];
    /** @var array<string, DatabaseDialectInterface> */
    private static array $dialectPool = [];

    private function __construct()
    {
    }

    /**
     * @return PDO|array{status:string,message:string,code:int|string}
     */
    public static function connection(?string $name = null)
    {
        $connectionName = self::normalizeConnectionName($name);

        if (array_key_exists($connectionName, self::$pool)) {
            return self::$pool[$connectionName];
        }

        $config = self::connectionConfig($connectionName);
        $result = self::resolveConnection($connectionName, $config)->connect($config);
        self::$pool[$connectionName] = $result;

        return $result;
    }

    public static function driver(?string $name = null): string
    {
        $config = self::connectionConfig($name);
        return strtolower(trim((string)($config['driver'] ?? 'mysql')));
    }

    public static function dialect(?string $name = null): DatabaseDialectInterface
    {
        $connectionName = self::normalizeConnectionName($name);
        if (array_key_exists($connectionName, self::$dialectPool)) {
            return self::$dialectPool[$connectionName];
        }

        $config = self::connectionConfig($connectionName);
        $dialect = self::resolveDialect($connectionName, $config);
        self::$dialectPool[$connectionName] = $dialect;

        return $dialect;
    }

    public static function defaultConnectionName(): string
    {
        if (defined('DB_DEFAULT_CONNECTION')) {
            $v = trim((string)DB_DEFAULT_CONNECTION);
            if ($v !== '') {
                return $v;
            }
        }
        if (defined('DB_CONNECTION')) {
            $v = trim((string)DB_CONNECTION);
            if ($v !== '') {
                return $v;
            }
        }
        return 'default';
    }

    public static function disconnect(?string $name = null): void
    {
        if ($name === null) {
            self::$pool = [];
            self::$dialectPool = [];
            return;
        }

        $connectionName = self::normalizeConnectionName($name);
        unset(self::$pool[$connectionName]);
        unset(self::$dialectPool[$connectionName]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function connectionConfig(?string $name = null): array
    {
        $connectionName = self::normalizeConnectionName($name);
        $connections = self::configuredConnections();

        if (!isset($connections[$connectionName]) || !is_array($connections[$connectionName])) {
            throw new Exception("Database connection '{$connectionName}' is not configured.", 500);
        }

        return $connections[$connectionName];
    }

    private static function normalizeConnectionName(?string $name): string
    {
        $normalized = trim((string)($name ?? self::defaultConnectionName()));
        return $normalized !== '' ? $normalized : self::defaultConnectionName();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function configuredConnections(): array
    {
        if (defined('DB_CONNECTIONS') && is_array(DB_CONNECTIONS) && DB_CONNECTIONS !== []) {
            return DB_CONNECTIONS;
        }

        $legacyDriver = defined('DB_DRIVER') ? strtolower(trim((string)DB_DRIVER)) : 'mysql';
        $defaultName = self::defaultConnectionName();
        if ($legacyDriver === 'sqlite') {
            return [
                $defaultName => [
                    'driver' => 'sqlite',
                    'path' => defined('DB_SQLITE_PATH') ? (string)DB_SQLITE_PATH : (ABSPATH . 'app/database/database.sqlite'),
                    'foreign_keys' => defined('DB_SQLITE_FOREIGN_KEYS') ? (bool)DB_SQLITE_FOREIGN_KEYS : true,
                ],
            ];
        }

        return [
            $defaultName => [
                'driver' => 'mysql',
                'host' => defined('DB_HOST') ? (string)DB_HOST : 'localhost',
                'port' => defined('DB_PORT') ? (int)DB_PORT : null,
                'database' => defined('DB_NAME') ? (string)DB_NAME : '',
                'username' => defined('DB_USER') ? (string)DB_USER : '',
                'password' => defined('DB_PASSWORD') ? (string)DB_PASSWORD : '',
                'charset' => defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4',
                'collation' => defined('DB_COLLATION') ? (string)DB_COLLATION : 'utf8mb4_unicode_ci',
                'auto_create' => defined('DB_AUTO_CREATE') ? (bool)DB_AUTO_CREATE : false,
            ],
        ];
    }

    private static function resolveConnection(string $connectionName, array $config): DatabaseConnectionInterface
    {
        $driver = strtolower(trim((string)($config['driver'] ?? 'mysql')));

        switch ($driver) {
            case 'sqlite':
                return new SqliteConnection();
            case 'mysql':
                return new MySqlConnection();
            default:
                throw new Exception("Unsupported database driver '{$driver}' in connection '{$connectionName}'.", 500);
        }
    }

    private static function resolveDialect(string $connectionName, array $config): DatabaseDialectInterface
    {
        $driver = strtolower(trim((string)($config['driver'] ?? 'mysql')));

        switch ($driver) {
            case 'sqlite':
                return new SqliteDialect();
            case 'mysql':
                return new MySqlDialect();
            default:
                throw new Exception("Unsupported database driver '{$driver}' in connection '{$connectionName}'.", 500);
        }
    }
}
