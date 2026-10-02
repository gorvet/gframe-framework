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
    'session' => [
        'name' => null,
        'driver' => 'native',
        'connection' => null,
        'lifetime' => 1800,
        'idle_timeout' => 1800,
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
        'max_upload_bytes' => 26214400,
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
    'notifications' => [
        'email' => [
            'max_attempts' => 5,
            'retry_delay_seconds' => 300,
        ],
    ],
];
