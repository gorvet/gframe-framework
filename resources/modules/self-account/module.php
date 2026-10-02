<?php

return [
    'name' => 'self-account',
    'type' => 'backend-module',
    'description' => 'Pantalla Mi cuenta para consultar el acceso, cambiar la contraseña y desactivar la cuenta propia.',
    'dependencies' => ['admin-panel', 'auth-ui', 'alerts', 'frontend-core'],
    'requires_schema' => ['auth'],
    'runtime' => [
        'root' => 'application/app',
        'namespace' => 'GFrame\\Modules\\SelfAccount',
    ],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/self-account'],
        ['source' => 'javascript', 'target' => 'js/modules/self-account'],
    ],
    'application' => [
        ['source' => 'application/routes/routes_account.php', 'target' => 'config/routes/routes_account.php'],
        ['source' => 'application/routes/routes_ajax_account.php', 'target' => 'config/routes/routes_ajax_account.php'],
    ],
];
