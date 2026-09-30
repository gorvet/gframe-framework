<?php

return [
    'name' => 'notifications-email',
    'type' => 'backend-module',
    'description' => 'Adaptador del sistema de notificaciones para el soporte Mail del núcleo.',
    'dependencies' => ['notifications', 'cron-runner'],
    'application' => [
        ['source' => 'application/mail', 'target' => 'app/views/templates/mail'],
        ['source' => 'application/cron', 'target' => 'config/cron'],
    ],
];
