<?php

return [
    'max_upload_bytes' => 25 * 1024 * 1024,
    'allowed_extensions' => [
        'images' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'docs' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'json'],
        'audios' => ['mp3', 'wav', 'ogg', 'opus', 'm4a', 'aac', 'flac'],
        'videos' => ['mp4', 'webm', 'mov', 'mkv', 'avi'],
    ],
    'variants' => [
        'small' => ['w' => 150, 'h' => 150, 'mode' => 'crop'],
        'medium' => ['w' => 300, 'h' => 300, 'mode' => 'fit'],
    ],
];
