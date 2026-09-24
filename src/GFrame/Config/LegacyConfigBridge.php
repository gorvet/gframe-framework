<?php

namespace GFrame\Config;

final class LegacyConfigBridge
{
    public static function defineConstants(): void
    {
        $siteUrl = trim((string)ConfigRepository::get('app.url', ''));
        if ($siteUrl === '') {
            $siteUrl = \UrlHelper::guessUrl();
        }
        $siteUrl = rtrim($siteUrl, '/') . '/';

        self::define('DebugMode', (bool)ConfigRepository::get('app.debug', false));
        self::define('site_name', (string)ConfigRepository::get('app.name', 'GFrame'));
        self::define('site_url', $siteUrl);
        self::define('SUPPORTED_LANGS', (array)ConfigRepository::get('app.supported_languages', ['es']));
        self::define('APP_TIMEZONE', (string)ConfigRepository::get('app.timezone', 'UTC'));
        self::define('session_name', self::sessionName($siteUrl));

        $defaultConnection = (string)ConfigRepository::get('database.default', 'main');
        $connections = (array)ConfigRepository::get('database.connections', []);
        self::define('DB_DEFAULT_CONNECTION', $defaultConnection);
        self::define('DB_CONNECTIONS', $connections);

        $database = is_array($connections[$defaultConnection] ?? null) ? $connections[$defaultConnection] : [];
        self::define('DB_DRIVER', (string)($database['driver'] ?? 'mysql'));
        self::define('DB_HOST', (string)($database['host'] ?? 'localhost'));
        if (isset($database['port'])) {
            self::define('DB_PORT', (int)$database['port']);
        }
        self::define('DB_NAME', (string)($database['database'] ?? ''));
        self::define('DB_USER', (string)($database['username'] ?? ''));
        self::define('DB_PASSWORD', (string)($database['password'] ?? ''));
        self::define('DB_CHARSET', (string)($database['charset'] ?? 'utf8mb4'));
        self::define('DB_COLLATION', (string)($database['collation'] ?? 'utf8mb4_unicode_ci'));
        self::define('DB_AUTO_CREATE', (bool)($database['auto_create'] ?? false));
        if (isset($database['path'])) {
            self::define('DB_SQLITE_PATH', (string)$database['path']);
        }
        if (isset($database['foreign_keys'])) {
            self::define('DB_SQLITE_FOREIGN_KEYS', (bool)$database['foreign_keys']);
        }

        self::defineTenancy();

        $allowIndexing = (bool)ConfigRepository::get('seo.allow_indexing', true);
        self::define('Metricool', (bool)ConfigRepository::get('seo.metricool', false));
        self::define('SEO_ALLOW_INDEXING', $allowIndexing);
        self::define('SEO_ENABLE_SITEMAP_XML', (bool)ConfigRepository::get('seo.sitemap', $allowIndexing));
        self::define('SEO_ENABLE_ROBOTS_TXT', (bool)ConfigRepository::get('seo.robots', $allowIndexing));
        self::define('SEO_ENABLE_LLMS_TXT', (bool)ConfigRepository::get('seo.llms', $allowIndexing));

        self::define('M_Host', (string)ConfigRepository::get('mail.host', ''));
        self::define('M_Port', (int)ConfigRepository::get('mail.port', 465));
        self::define('M_Username', (string)ConfigRepository::get('mail.username', ''));
        self::define('M_Password', (string)ConfigRepository::get('mail.password', ''));
        self::define('M_Secure', (string)ConfigRepository::get('mail.encryption', 'ssl'));
        self::define('M_From', (string)ConfigRepository::get('mail.from', 'noreply@example.test'));
        self::define('M_Name', (string)ConfigRepository::get('mail.from_name', ConfigRepository::get('app.name', 'GFrame')));

        date_default_timezone_set((string)APP_TIMEZONE);
    }

    private static function defineTenancy(): void
    {
        $key = trim((string)ConfigRepository::get('tenancy.key', ''));
        $table = trim((string)ConfigRepository::get('tenancy.table', ''));
        if (($key === '') !== ($table === '')) {
            throw new \RuntimeException('La configuración de tenancy requiere definir key y table conjuntamente.');
        }
        if ($key !== '') {
            self::define('TENANT', $key);
            self::define('TENANT_TABLE', $table);
        }
    }

    private static function sessionName(string $siteUrl): string
    {
        $configured = trim((string)ConfigRepository::get('session.name', ''));
        if ($configured !== '') {
            return $configured;
        }

        $base = strtolower($siteUrl);
        $base = (string)preg_replace('#^https?://#i', '', $base);
        $base = (string)preg_replace('/[^a-z0-9_]+/i', '_', $base);
        $base = trim($base, '_') ?: 'gframe';
        return 'gf_' . substr($base, 0, 48);
    }

    private static function define(string $name, mixed $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
