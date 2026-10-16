<?php

$editorAssets = require \GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.meta.php', 'rich-text-editor');

return [
    'css' => [
        ...($editorAssets['css'] ?? []),
        'public/vendors/external/sweetalert2/sweetalert2.min.css',
        'public/vendors/external/sweetalert2/sweetTheme.css',
    ],
    'hjs' => $editorAssets['hjs'] ?? [],
    'js' => [
        'public/vendors/external/sweetalert2/sweetalert2.all.min.js',
        'public/vendors/internal/gframe-alerts/alertToast.js',
        ...($editorAssets['js'] ?? []),
    ],
];
