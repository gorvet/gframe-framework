<?php

return [
    'app' => [
        'name' => 'GFrame',
        'environment' => 'production',
        'debug' => false,
        'public' => true,
        'url' => null,
        'timezone' => 'UTC',
        'language' => 'es',
        'supported_languages' => ['es'],
    ],
    'database' => [
        'default' => 'main',
        'connections' => [],
    ],
    'security' => [
        'trusted_proxies' => [],
        'headers' => [
            'enabled' => true,
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'frame_options' => null,
            'content_security_policy' => null,
            'hsts_max_age' => 0,
            'hsts_include_subdomains' => false,
        ],
        // Caducidad del token CSRF (en segundos) SOLO para sesiones de invitado
        // (formularios públicos). Para usuarios autenticados el token caduca con
        // la sesión (logout o inactividad), nunca con un temporizador propio.
        // 0 lo desactiva también para invitados (comportamiento histórico).
        'csrf_guest_max_age' => 0,
    ],
    'cron' => [
        'mode' => 'real',
        'executor' => 'cli',
        'min_interval' => 60,
        'batch_limit' => 10,
    ],
    'session' => [
        'name' => null,
        'driver' => 'native',
        'connection' => null,
        'lifetime' => 1800,
        'idle_timeout' => 1800,
        'cookie_httponly' => true,
        'cookie_secure' => null,
        'cookie_samesite' => null,
        'cookie_path' => null,
        'cookie_domain' => null,
        'cookie_lifetime' => null,
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
            'timeout' => 2.0,
            'prefix' => 'gframe:session:',
        ],
    ],
    'auth' => [
        'rate_limit' => [
            'enabled' => true,
            'window_seconds' => 900,
            'ip_attempts' => 100,
            'login_attempts' => 10,
            'mail_attempts' => 5,
        ],
        'registration_admin_notice' => [
            'enabled' => false,
            'user_ids' => [],
            'channels' => ['inbox'],
        ],
        'deactivation' => [
            'retention_days' => 60,
            'warning_hours' => 72,
        ],
        'login_redirect' => '',
        'password_change_redirect' => 'account',
        'password_expiration' => [
            'enabled' => false,
            'days' => 90,
            'warning_days' => 7,
        ],
    ],
    'tenancy' => [
        'key' => null,
        'table' => null,
    ],
    'media' => [
        'scope' => 'global',
        'quota_bytes' => 0,
    ],
    'seo' => [
        'enabled' => true,
        'allow_indexing' => true,
        'sitemap' => true,
        'robots' => true,
        'llms' => true,
    ],
    'analytics' => [
        'enabled' => false,
        'metricool' => [
            'enabled' => false,
            'hash' => '',
        ],
    ],
    'mail' => [
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,
            'window_seconds' => 3600,
        ],
    ],
    'content' => ['revisions' => ['limit' => 10]],
    'notifications' => [
        'queue' => ['lease_seconds' => 900],
        'email' => [
            'max_attempts' => 5,
            'retry_delay_seconds' => 300,
        ],
    ],
];
