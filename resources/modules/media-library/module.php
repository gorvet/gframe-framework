<?php

return [
    'name' => 'media-library',
    'type' => 'backend-module',
    'description' => 'Biblioteca multimedia global o por tenant con almacenamiento seguro y procesamiento de archivos.',
    'dependencies' => ['self-account', 'alerts', 'frontend-core'],
    'schemas' => [
        'mysql' => 'database/mysql.sql',
        'sqlite' => 'database/sqlite.sql',
    ],
    'migrations' => [
        'mysql' => ['database/migrations/mysql/202609280001_media_metadata.sql', 'database/migrations/mysql/202609290001_media_remote_url.sql'],
        'sqlite' => ['database/migrations/sqlite/202609280001_media_metadata.sql', 'database/migrations/sqlite/202609290001_media_remote_url.sql'],
    ],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/media-library'],
        ['source' => 'javascript', 'target' => 'js/modules/media-library'],
    ],
    'application' => [
        ['source' => 'application/controllers/MediaController.php', 'target' => 'app/controllers/admin/media/MediaController.php'],
        ['source' => 'application/routes/routes_admin_media.php', 'target' => 'config/routes/routes_admin_media.php'],
        ['source' => 'application/routes/routes_ajax_admin_media.php', 'target' => 'config/routes/routes_ajax_admin_media.php'],
        ['source' => 'application/views', 'target' => 'app/views/admin/media'],
        ['source' => 'application/components', 'target' => 'app/views/components/media'],
    ],
];
