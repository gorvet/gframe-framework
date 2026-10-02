<?php

$scripts = [
    'public/vendors/external/jquery/jquery.min.js',
    'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
    'public/vendors/external/sweetalert2/sweetalert2.all.min.js',
    'public/vendors/internal/gframe-alerts/alertToast.js',
    'public/js/core/heartbeat.js',
    'public/js/core/session.js',
];

return [
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/vendors/external/sweetalert2/sweetalert2.min.css',
        'public/css/variables.css',
        'public/css/bootstrap-buttons-compat.css',
        'public/css/common.css',
        'public/vendors/external/sweetalert2/sweetTheme.css',
        'public/vendors/internal/gframe-icons/style.css',
        'public/vendors/internal/gframe-alerts/alertToast.css',
    ],
    'js' => array_values(array_filter($scripts, static fn(string $script): bool => is_file(ABSPATH . $script))),
];
