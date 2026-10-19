<?php
return [
    'name' => 'content-revisions', 'type' => 'backend-module',
    'install_profiles' => ['managed', 'intranet', 'saas'],
    'description' => 'Historial de contenido, comparación, restauración y vaciado autorizado.',
    'dependencies' => ['self-account'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\ContentRevisions'],
    'schemas' => ['mysql' => 'database/mysql.sql', 'sqlite' => 'database/sqlite.sql'],
    'migrations' => ['mysql' => ['database/migrations/mysql/202610190001_content_revisions.sql'], 'sqlite' => ['database/migrations/sqlite/202610190001_content_revisions.sql']],
];
