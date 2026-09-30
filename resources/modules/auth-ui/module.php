<?php

return [
    'name' => 'auth-ui',
    'type' => 'backend-module',
    'description' => 'Pantalla y rutas de acceso para aplicaciones con autenticación.',
    'dependencies' => ['alerts', 'frontend-core', 'heartbeat-client', 'password-utils'],
    'requires_schema' => ['auth'],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202609290001_managed_sessions.sql', 'database/migrations/mysql/202609290002_role_security_version.sql', 'database/migrations/mysql/202609290003_permission_templates_and_memberships.sql'],
        'sqlite' => ['database/migrations/sqlite/202609290001_managed_sessions.sql', 'database/migrations/sqlite/202609290002_role_security_version.sql', 'database/migrations/sqlite/202609290003_permission_templates_and_memberships.sql'],
    ],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/auth'],
        ['source' => 'javascript', 'target' => 'js/modules/auth'],
    ],
    'application' => [
        ['source' => 'application/controllers/AuthController.php', 'target' => 'app/controllers/auth/AuthController.php'],
        ['source' => 'application/routes/routes_auth.php', 'target' => 'config/routes/routes_auth.php'],
        ['source' => 'application/routes/routes_ajax_auth.php', 'target' => 'config/routes/routes_ajax_auth.php'],
        ['source' => 'application/views/auth.group.meta.php', 'target' => 'app/views/auth/auth.group.meta.php'],
        ['source' => 'application/views/authLogin.php', 'target' => 'app/views/auth/authLogin.php'],
        ['source' => 'application/views/authRegister.php', 'target' => 'app/views/auth/authRegister.php'],
        ['source' => 'application/views/authRecovery.php', 'target' => 'app/views/auth/authRecovery.php'],
        ['source' => 'application/views/authReset.php', 'target' => 'app/views/auth/authReset.php'],
        ['source' => 'application/views/authVerify.php', 'target' => 'app/views/auth/authVerify.php'],
        ['source' => 'application/views/authTemplate.php', 'target' => 'app/views/templates/authTemplate.php'],
    ],
];
