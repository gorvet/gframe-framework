<?php

return [
    'name' => 'heartbeat-client',
    'type' => 'internal-ui',
    'description' => 'Cliente compartido para heartbeat, sesión y canales activos.',
    'dependencies' => ['jquery', 'alerts'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\HeartbeatClient'],
    'assets' => [
        ['source' => 'public', 'target' => 'js/core'],
    ],
    'application' => [
        ['source' => 'application/routes/routes_system_heartbeat.php', 'target' => 'config/routes/routes_system_heartbeat.php'],
    ],
];
