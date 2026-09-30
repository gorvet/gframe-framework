<?php

return [
    'name' => 'admin-panel',
    'type' => 'frontend-module',
    'description' => 'Estructura compartida del panel administrativo, sidebar y tema.',
    'dependencies' => ['auth-ui', 'gframe-icons', 'bootstrap', 'jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/admin-panel'],
        ['source' => 'javascript', 'target' => 'js/modules/admin-panel'],
    ],
    'application' => [
        ['source' => 'application/controllers/AdminController.php', 'target' => 'app/controllers/admin/AdminController.php'],
        ['source' => 'application/routes/routes_admin_dashboard.php', 'target' => 'config/routes/routes_admin_dashboard.php'],
        ['source' => 'application/views/adminIndex.php', 'target' => 'app/views/admin/adminIndex.php'],
        ['source' => 'application/views/admin.group.meta.php', 'target' => 'app/views/admin/admin.group.meta.php'],
        ['source' => 'application/templates/adminTemplate.php', 'target' => 'app/views/templates/adminTemplate.php'],
        ['source' => 'application/templates/admin.meta.php', 'target' => 'app/views/templates/admin.meta.php'],
        ['source' => 'application/parts/navbar.php', 'target' => 'app/views/admin/parts/navbar.php'],
        ['source' => 'application/parts/aside.php', 'target' => 'app/views/admin/parts/aside.php'],
        ['source' => 'application/parts/menu.php', 'target' => 'app/views/admin/parts/menu.php'],
    ],
];
