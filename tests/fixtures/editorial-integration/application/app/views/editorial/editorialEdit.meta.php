<?php
return array_merge_recursive(
    require GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.meta.php', 'rich-text-editor'),
    ['js' => ['public/js/editorial-integration.js']]
);
