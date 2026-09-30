<?php

return [
    'static' => [
        'name' => 'Sitio sin base de datos',
        'description' => 'Landing, documentación o sitio público sin administración.',
        'database' => false,
        'auth' => false,
        'tenancy' => false,
        'public' => true,
        'modules' => [],
    ],
    'managed' => [
        'name' => 'Aplicación administrada',
        'description' => 'Aplicación con base de datos, autenticación y administración global.',
        'database' => true,
        'auth' => true,
        'tenancy' => false,
        'public' => true,
        'modules' => ['auth-ui', 'self-account', 'admin-panel', 'user-admin', 'media-library'],
    ],
    'intranet' => [
        'name' => 'Intranet privada',
        'description' => 'Aplicación administrada sin vista pública y con acceso autenticado.',
        'database' => true,
        'auth' => true,
        'tenancy' => false,
        'public' => false,
        'modules' => ['auth-ui', 'self-account', 'admin-panel', 'user-admin', 'media-library'],
    ],
    'saas' => [
        'name' => 'Aplicación SaaS',
        'description' => 'Aplicación multitenant con una base de datos compartida.',
        'database' => true,
        'auth' => true,
        'tenancy' => true,
        'public' => true,
        'modules' => ['auth-ui', 'self-account', 'admin-panel', 'user-admin', 'media-library', 'notifications', 'cron-runner'],
    ],
];
