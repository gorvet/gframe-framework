<?php

return [
    'name' => 'heartbeat-client',
    'type' => 'internal-ui',
    'description' => 'Cliente compartido para heartbeat, sesión y canales activos.',
    'dependencies' => ['jquery', 'alerts'],
    'assets' => [
        ['source' => 'public', 'target' => 'js/core'],
    ],
    'application' => [
        ['source' => 'application/controllers/HeartbeatController.php', 'target' => 'app/controllers/system/heartbeat/HeartbeatController.php'],
        ['source' => 'application/routes/routes_system_heartbeat.php', 'target' => 'config/routes/routes_system_heartbeat.php'],
    ],
];
