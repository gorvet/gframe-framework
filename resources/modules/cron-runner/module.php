<?php

return [
    'name' => 'cron-runner',
    'type' => 'backend-module',
    'description' => 'Planificador y punto de entrada CLI para tareas programadas.',
    'schemas' => [
        'mysql' => 'database/mysql.sql',
        'sqlite' => 'database/sqlite.sql',
    ],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202609280003_cron_recurrence.sql'],
        'sqlite' => ['database/migrations/sqlite/202609280003_cron_recurrence.sql'],
    ],
    'application' => [
        ['source' => 'application/bin/gframe-cron.php', 'target' => 'bin/gframe-cron.php'],
    ],
];
