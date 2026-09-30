<?php

return [
    'name' => 'notifications',
    'type' => 'backend-module',
    'description' => 'Inbox de notificaciones con despachador y transportes extensibles.',
    'dependencies' => ['self-account', 'frontend-core'],
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
        ['source' => 'application/controllers/NotificationController.php', 'target' => 'app/controllers/notifications/NotificationController.php'],
        ['source' => 'application/routes/routes_ajax_notifications.php', 'target' => 'config/routes/routes_ajax_notifications.php'],
        ['source' => 'application/routes/routes_web_notifications.php', 'target' => 'config/routes/routes_web_notifications.php'],
        ['source' => 'application/views', 'target' => 'app/views/components/notifications'],
        ['source' => 'application/history', 'target' => 'app/views/notifications'],
        ['source' => 'application/admin/notifications.php', 'target' => 'app/views/admin/parts/header-actions/notifications.php'],
        ['source' => 'application/admin/notifications.meta.php', 'target' => 'app/views/templates/meta/admin/notifications.meta.php'],
    ],
];
