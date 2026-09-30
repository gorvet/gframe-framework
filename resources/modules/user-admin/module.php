<?php

return [
    'name' => 'user-admin',
    'type' => 'backend-module',
    'description' => 'Administración de usuarios y asignación protegida de roles.',
    'dependencies' => ['self-account', 'admin-panel', 'alerts', 'frontend-core'],
    'requires_schema' => ['auth'],
    'assets' => [
        ['source' => 'javascript', 'target' => 'js/modules/user-admin'],
    ],
    'application' => [
        ['source' => 'application/controllers/UserAdminController.php', 'target' => 'app/controllers/admin/users/UserAdminController.php'],
        ['source' => 'application/routes/routes_admin_users.php', 'target' => 'config/routes/routes_admin_users.php'],
        ['source' => 'application/routes/routes_ajax_admin_users.php', 'target' => 'config/routes/routes_ajax_admin_users.php'],
        ['source' => 'application/views', 'target' => 'app/views/admin/users'],
        ['source' => 'application/admin/users-menu.php', 'target' => 'app/views/admin/parts/menu-items/users.php'],
    ],
];
