<?php

return [
    'name' => 'content-editing',
    'type' => 'backend-module',
    'install_profiles' => ['managed', 'intranet', 'saas'],
    'description' => 'Aviso de otras sesiones editoriales, con guardado por revisión optativo.',
    'dependencies' => ['self-account', 'jquery'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\ContentEditing'],
    'assets' => [['source' => 'public', 'target' => 'js/app/admin/components']],
    'schemas' => ['mysql' => 'database/mysql.sql', 'sqlite' => 'database/sqlite.sql'],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202610190001_content_presence.sql'],
        'sqlite' => ['database/migrations/sqlite/202610190001_content_presence.sql'],
    ],
];
