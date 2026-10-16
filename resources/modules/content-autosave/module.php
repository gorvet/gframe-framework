<?php

return [
    'name' => 'content-autosave',
    'type' => 'backend-module',
    'install_profiles' => ['managed', 'intranet', 'saas'],
    'description' => 'Autoguardado privado de contenido, con versiones y recuperación explícita.',
    'dependencies' => ['self-account', 'rich-text-editor'],
    'schemas' => ['mysql' => 'database/mysql.sql', 'sqlite' => 'database/sqlite.sql'],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202610150001_content_autosaves.sql'],
        'sqlite' => ['database/migrations/sqlite/202610150001_content_autosaves.sql'],
    ],
];
