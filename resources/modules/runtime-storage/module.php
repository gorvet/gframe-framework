<?php

return [
    'name' => 'runtime-storage',
    'type' => 'backend-module',
    'description' => 'Persistencia SQL del estado temporal y sesiones de los módulos.',
    'schemas' => [
        'mysql' => 'database/migrations/mysql/202611030002_runtime_storage.sql',
        'sqlite' => 'database/migrations/sqlite/202611030002_runtime_storage.sql',
    ],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202611030002_runtime_storage.sql'],
        'sqlite' => ['database/migrations/sqlite/202611030002_runtime_storage.sql'],
    ],
];
