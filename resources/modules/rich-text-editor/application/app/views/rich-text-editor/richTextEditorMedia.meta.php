<?php

$editorAssets = require \GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.meta.php', 'rich-text-editor');
return [
    'css' => [...$editorAssets['css'], 'public/css/modules/media-library/media-library.css', 'public/css/modules/media-library/media-content.css'],
    'hjs' => $editorAssets['hjs'],
    'js' => [
        'public/js/modules/media-library/media-library.js',
        'public/js/modules/media-library/media-picker.js',
        ...$editorAssets['js'],
    ],
];
