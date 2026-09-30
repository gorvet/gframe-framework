<?php

return [
    'name' => 'notification-campaigns',
    'type' => 'backend-module',
    'description' => 'Campañas masivas inmediatas o programadas mediante transportes de notificaciones.',
    'dependencies' => ['notifications', 'cron-runner', 'alerts', 'frontend-core'],
    'schemas' => ['mysql' => 'database/mysql.sql', 'sqlite' => 'database/sqlite.sql'],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202609280004_notification_campaigns.sql'],
        'sqlite' => ['database/migrations/sqlite/202609280004_notification_campaigns.sql'],
    ],
    'assets' => [
        ['source' => 'javascript', 'target' => 'js/modules/notification-campaigns'],
        ['source' => 'public', 'target' => 'css/modules/notification-campaigns'],
    ],
    'application' => [
        ['source' => 'application/controllers/CampaignController.php', 'target' => 'app/controllers/admin/notifications/CampaignController.php'],
        ['source' => 'application/routes/routes_admin_notification_campaigns.php', 'target' => 'config/routes/routes_admin_notification_campaigns.php'],
        ['source' => 'application/routes/routes_ajax_notification_campaigns.php', 'target' => 'config/routes/routes_ajax_notification_campaigns.php'],
        ['source' => 'application/views', 'target' => 'app/views/admin/notifications/campaigns'],
    ],
];
