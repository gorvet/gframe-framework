<?php

return [
    'name' => 'user-admin',
    'type' => 'backend-module',
    'description' => 'Administración de usuarios y asignación protegida de roles.',
    'dependencies' => ['admin-panel', 'alerts', 'frontend-core'],
    'requires_schema' => ['auth'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\UserAdmin'],
    'assets' => [
        ['source' => 'javascript', 'target' => 'js/modules/user-admin'],
    ],
    'application' => [
        ['source' => 'application/routes/routes_admin_users.php', 'target' => 'config/routes/routes_admin_users.php'],
        ['source' => 'application/routes/routes_ajax_admin_users.php', 'target' => 'config/routes/routes_ajax_admin_users.php'],
        ['source' => 'application/admin/users-menu.php', 'target' => 'app/views/admin-panel/parts/menu-items/users.php'],
    ],
];
