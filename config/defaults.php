<?php

return [
    'app' => [
        'name' => 'GFrame',
        'environment' => 'production',
        'debug' => false,
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
    ],
    'auth' => [
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
        'host' => '',
        'port' => 465,
        'username' => '',
        'password' => '',
        'encryption' => 'ssl',
        'from' => 'noreply@example.test',
        'from_name' => 'GFrame',
    ],
];
