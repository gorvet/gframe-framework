<?php

return [
    'name' => 'admin-panel',
    'type' => 'frontend-module',
    'description' => 'Estructura compartida del panel administrativo, sidebar y tema.',
    'dependencies' => ['auth-ui', 'gframe-icons', 'bootstrap', 'jquery'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\AdminPanel', 'templates' => ['admin']],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/admin-panel'],
        ['source' => 'javascript', 'target' => 'js/modules/admin-panel'],
    ],
    'application' => [
        ['source' => 'application/routes/routes_admin_dashboard.php', 'target' => 'config/routes/routes_admin_dashboard.php'],
    ],
];
