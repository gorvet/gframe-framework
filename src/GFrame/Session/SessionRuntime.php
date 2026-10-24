<?php

namespace GFrame\Session;

use GFrame\Config\ConfigRepository;

final class SessionRuntime
{
    private static ?ActiveSessionRegistry $registry = null;
    private static bool $authorizationStale = false;

    public static function start(string $projectRoot): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            \GFrame\Security\CsrfToken::ensure();
            return;
        }
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
        ini_set('session.use_only_cookies', '1');
        self::applyCookieParams();
        $name = trim((string)ConfigRepository::get('session.name', ''));
        if ($name === '' && defined('session_name')) {
            $name = trim((string)constant('session_name'));
        }
        if ($name !== '') session_name($name);
        session_start();
        \GFrame\Security\CsrfToken::ensure();
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

    /**
     * Fija las banderas de la cookie de sesión antes de iniciarla. Los valores
     * seguros son el predeterminado y pueden ajustarse por configuración:
     * HttpOnly activo, SameSite=Lax y Secure automático bajo HTTPS. Debe
     * invocarse antes de session_start().
     */
    private static function applyCookieParams(): void
    {
        if (!filter_var(ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN)) return;
        $current = session_get_cookie_params();
        $sameSite = trim((string)(ConfigRepository::get('session.cookie_samesite') ?? ($current['samesite'] ?: 'Lax')));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }

        // null (predeterminado) = automático según HTTPS; un booleano explícito lo fuerza.
        $secure = ConfigRepository::get('session.cookie_secure', null);
        $secure = $secure === null ? ($current['secure'] || \GFrame\Http\RequestSecurity::isSecure()) : (bool)$secure;
        if ($sameSite === 'None') $secure = true;

        session_set_cookie_params([
            'lifetime' => max(0, (int)(ConfigRepository::get('session.cookie_lifetime') ?? $current['lifetime'])),
            'path' => (string)(ConfigRepository::get('session.cookie_path') ?? $current['path']),
            'domain' => (string)(ConfigRepository::get('session.cookie_domain') ?? $current['domain']),
            'secure' => $secure,
            'httponly' => (bool)ConfigRepository::get('session.cookie_httponly', true),
            'samesite' => $sameSite,
        ]);
    }

    private static function lifetime(): int
    {
        return max(60, (int)ConfigRepository::get('session.lifetime', ConfigRepository::get('session.idle_timeout', 1800)));
    }
}
