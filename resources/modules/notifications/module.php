<?php

return [
    'name' => 'notifications',
    'type' => 'backend-module',
    'description' => 'Inbox de notificaciones con despachador y transportes extensibles.',
    'dependencies' => ['self-account', 'frontend-core', 'cron-runner'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\Notifications'],
    'schemas' => [
        'mysql' => 'database/mysql.sql',
        'sqlite' => 'database/sqlite.sql',
    ],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202609280001_notifications_inbox.sql'],
        'sqlite' => ['database/migrations/sqlite/202609280001_notifications_inbox.sql'],
    ],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/notifications'],
        ['source' => 'javascript', 'target' => 'js/modules/notifications'],
    ],
    'application' => [
        ['source' => 'application/cron', 'target' => 'config/cron'],
        ['source' => 'application/routes/routes_ajax_notifications.php', 'target' => 'config/routes/routes_ajax_notifications.php'],
        ['source' => 'application/routes/routes_web_notifications.php', 'target' => 'config/routes/routes_web_notifications.php'],
        ['source' => 'application/admin/notifications.php', 'target' => 'app/views/admin-panel/parts/header-actions/notifications.php'],
        ['source' => 'application/admin/notifications-menu.php', 'target' => 'app/views/admin-panel/parts/menu-items/notifications.php'],
        ['source' => 'application/admin/notifications.meta.php', 'target' => 'app/views/templates/meta/admin/notifications.meta.php'],
    ],
];
