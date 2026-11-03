<?php

return [
    'name' => 'rate-limits',
    'type' => 'backend-module',
    'dependencies' => ['runtime-storage'],
    'description' => 'Contadores temporales de autenticación y correo en base de datos.',
    'schemas' => [
        'mysql' => 'database/migrations/mysql/202611030001_rate_limits.sql',
        'sqlite' => 'database/migrations/sqlite/202611030001_rate_limits.sql',
    ],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202611030001_rate_limits.sql'],
        'sqlite' => ['database/migrations/sqlite/202611030001_rate_limits.sql'],
    ],
];
