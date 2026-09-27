<?php

return [
    'name' => 'notifications',
    'type' => 'backend-module',
    'description' => 'Cola multicanal con transporte y persistencia intercambiables.',
    'schemas' => [
        'mysql' => 'database/mysql.sql',
        'sqlite' => 'database/sqlite.sql',
    ],
];
