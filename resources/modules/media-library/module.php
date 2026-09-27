<?php

return [
    'name' => 'media-library',
    'type' => 'backend-module',
    'description' => 'Biblioteca multimedia global o por tenant con almacenamiento seguro y procesamiento de archivos.',
    'dependencies' => ['alerts', 'frontend-core'],
    'schemas' => [
        'mysql' => 'database/mysql.sql',
        'sqlite' => 'database/sqlite.sql',
    ],
];
