<?php

return [
    'metaTags' => [
        'title' => 'Acceder - ' . site_name,
        'description' => 'Acceso a ' . site_name . '.',
        'robots' => 'noindex, nofollow',
    ],
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/css/variables.css',
        'public/vendors/internal/gframe-icons/style.css',
        'public/vendors/internal/gframe-alerts/alertToast.css',
        'public/css/modules/auth/auth.css',
        'public/vendors/internal/passwordUtils/passwordUtils.css',
    ],
    'js' => [
        'public/vendors/external/jquery/jquery.min.js',
        'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
        'public/vendors/internal/gframe-alerts/alertToast.js',
        'public/js/core/utils/forms.js',
        'public/js/core/utils/errors.js',
        'public/vendors/internal/passwordUtils/passwordUtils.js',
        'public/js/modules/auth/auth.js',
    ],
];
