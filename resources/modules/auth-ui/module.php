<?php

return [
    'name' => 'auth-ui',
    'type' => 'backend-module',
    'description' => 'Pantalla y rutas de acceso para aplicaciones con autenticación.',
    'dependencies' => ['alerts', 'frontend-core', 'heartbeat-client', 'password-utils'],
    'requires_schema' => ['auth'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\AuthUi', 'templates' => ['auth']],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202609290001_managed_sessions.sql', 'database/migrations/mysql/202609290002_role_security_version.sql', 'database/migrations/mysql/202609290003_permission_templates_and_memberships.sql'],
        'sqlite' => ['database/migrations/sqlite/202609290001_managed_sessions.sql', 'database/migrations/sqlite/202609290002_role_security_version.sql', 'database/migrations/sqlite/202609290003_permission_templates_and_memberships.sql'],
    ],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/auth'],
        ['source' => 'javascript', 'target' => 'js/modules/auth'],
    ],
    'application' => [
        ['source' => 'application/routes/routes_auth.php', 'target' => 'config/routes/routes_auth.php'],
        ['source' => 'application/routes/routes_ajax_auth.php', 'target' => 'config/routes/routes_ajax_auth.php'],
    ],
];
