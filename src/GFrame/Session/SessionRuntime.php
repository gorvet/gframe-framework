<?php

namespace GFrame\Session;

use GFrame\Config\ConfigRepository;

final class SessionRuntime
{
    private static ?ActiveSessionRegistry $registry = null;
    private static bool $authorizationStale = false;

    public static function start(string $projectRoot): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $driver = mb_strtolower(trim((string)ConfigRepository::get('session.driver', 'native')), 'UTF-8');
        $handler = match ($driver) {
            'database' => self::databaseHandler(),
            'redis' => self::redisHandler(),
            'native' => null,
            default => throw new \RuntimeException("Driver de sesión no compatible: {$driver}."),
        };
        if ($handler !== null) {
            self::$registry = $handler;
            session_set_save_handler($handler, true);
        }
        ini_set('session.use_strict_mode', '1');
        $name = trim((string)ConfigRepository::get('session.name', ''));
        if ($name === '' && defined('session_name')) {
            $name = trim((string)constant('session_name'));
        }
        if ($name !== '') session_name($name);
        session_start();
    }

    public static function registry(): ?ActiveSessionRegistry
    {
        return self::$registry;
    }

    public static function markAuthorizationStale(): void { self::$authorizationStale = true; }
    public static function authorizationStale(): bool { return self::$authorizationStale; }
    public static function clearAuthorizationStale(): void { self::$authorizationStale = false; }

    public static function publishRoleVersion(int $roleID, int $version): void
    {
        if ((string)ConfigRepository::get('session.driver', 'native') !== 'redis') return;
        (self::$registry instanceof RedisSessionHandler ? self::$registry : self::redisHandler())->publishRoleVersion($roleID, $version);
    }

    private static function databaseHandler(): DatabaseSessionHandler
    {
        $connection = trim((string)ConfigRepository::get('session.connection', ''));
        $database = \DatabaseManager::connection($connection !== '' ? $connection : null);
        if (!$database instanceof \PDO) throw new \RuntimeException('No se pudo abrir el almacenamiento de sesiones.');
        return new DatabaseSessionHandler($database, self::lifetime());
    }

    private static function redisHandler(): RedisSessionHandler
    {
        if (!class_exists('Redis')) throw new \RuntimeException('El driver redis requiere la extensión phpredis.');
        $redis = new \Redis();
        $host = (string)ConfigRepository::get('session.redis.host', '127.0.0.1');
        $port = (int)ConfigRepository::get('session.redis.port', 6379);
        $timeout = (float)ConfigRepository::get('session.redis.timeout', 2.0);
        if (!$redis->connect($host, $port, $timeout)) throw new \RuntimeException('No se pudo conectar al almacenamiento Redis de sesiones.');
        $password = (string)ConfigRepository::get('session.redis.password', '');
        if ($password !== '' && !$redis->auth($password)) throw new \RuntimeException('Redis rechazó las credenciales de sesiones.');
        $redis->select((int)ConfigRepository::get('session.redis.database', 0));
        return new RedisSessionHandler($redis, (string)ConfigRepository::get('session.redis.prefix', 'gframe:session:'), self::lifetime());
    }

    private static function lifetime(): int
    {
        return max(60, (int)ConfigRepository::get('session.lifetime', ConfigRepository::get('session.idle_timeout', 1800)));
    }
}
