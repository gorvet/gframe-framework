<?php

return [
    'name' => 'self-account',
    'type' => 'backend-module',
    'description' => 'Pantalla Mi cuenta para consultar el acceso, cambiar la contraseña y desactivar la cuenta propia.',
    'dependencies' => ['auth-ui', 'alerts', 'frontend-core'],
    'requires_schema' => ['auth'],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/self-account'],
        ['source' => 'javascript', 'target' => 'js/modules/self-account'],
    ],
    'application' => [
        ['source' => 'application/controllers/SelfAccountController.php', 'target' => 'app/controllers/account/SelfAccountController.php'],
        ['source' => 'application/routes/routes_account.php', 'target' => 'config/routes/routes_account.php'],
        ['source' => 'application/routes/routes_ajax_account.php', 'target' => 'config/routes/routes_ajax_account.php'],
        ['source' => 'application/views', 'target' => 'app/views/account'],
        ['source' => 'application/templates/accountTemplate.php', 'target' => 'app/views/templates/accountTemplate.php'],
    ],
];
