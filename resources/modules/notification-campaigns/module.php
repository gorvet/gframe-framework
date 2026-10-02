<?php

return [
    'name' => 'notification-campaigns',
    'type' => 'backend-module',
    'description' => 'Campañas masivas inmediatas o programadas mediante transportes de notificaciones.',
    'dependencies' => ['notifications', 'cron-runner', 'alerts', 'frontend-core', 'flatpickr', 'gfselect'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\NotificationCampaigns'],
    'schemas' => ['mysql' => 'database/mysql.sql', 'sqlite' => 'database/sqlite.sql'],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202609280004_notification_campaigns.sql', 'database/migrations/mysql/202609300001_notification_campaign_rules.sql', 'database/migrations/mysql/202609300002_campaign_cooldown.sql', 'database/migrations/mysql/202609300003_account_deactivations.sql', 'database/migrations/mysql/202609300004_campaign_options_recurrence.sql', 'database/migrations/mysql/202609300005_campaign_edit_history.sql'],
        'sqlite' => ['database/migrations/sqlite/202609280004_notification_campaigns.sql', 'database/migrations/sqlite/202609300001_notification_campaign_rules.sql', 'database/migrations/sqlite/202609300002_campaign_cooldown.sql', 'database/migrations/sqlite/202609300003_account_deactivations.sql', 'database/migrations/sqlite/202609300004_campaign_options_recurrence.sql', 'database/migrations/sqlite/202609300005_campaign_edit_history.sql'],
    ],
    'assets' => [
        ['source' => 'javascript', 'target' => 'js/modules/notification-campaigns'],
        ['source' => 'public', 'target' => 'css/modules/notification-campaigns'],
    ],
    'application' => [
        ['source' => 'application/admin/campaigns-menu.php', 'target' => 'app/views/admin-panel/parts/menu-items/campaigns.php'],
        ['source' => 'application/routes/routes_admin_notification_campaigns.php', 'target' => 'config/routes/routes_admin_notification_campaigns.php'],
        ['source' => 'application/routes/routes_ajax_notification_campaigns.php', 'target' => 'config/routes/routes_ajax_notification_campaigns.php'],
    ],
];
