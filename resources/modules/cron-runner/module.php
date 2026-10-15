<?php

return [
    'name' => 'cron-runner',
    'type' => 'backend-module',
    'description' => 'Planificador compartido para cron real o virtual y ejecución CLI.',
    'schemas' => [
        'mysql' => 'database/mysql.sql',
        'sqlite' => 'database/sqlite.sql',
    ],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202610150000_cron_install.sql', 'database/migrations/mysql/202609280003_cron_recurrence.sql', 'database/migrations/mysql/202610150001_cron_runtime.sql'],
        'sqlite' => ['database/migrations/sqlite/202610150000_cron_install.sql', 'database/migrations/sqlite/202609280003_cron_recurrence.sql', 'database/migrations/sqlite/202610150001_cron_runtime.sql'],
    ],
    'application' => [
        ['source' => 'application/bin/gframe-cron.php', 'target' => 'bin/gframe-cron.php'],
    ],
];
