<?php

$authScripts = [
    'authLogin' => 'AuthLogin.js',
    'authRegister' => 'AuthRegister.js',
    'authRecovery' => 'AuthLostpassword.js',
    'authReset' => 'AuthResetpassword.js',
];
$authScript = $authScripts[(string)($routeParams['view'] ?? '')] ?? null;
$authTitles = [
    'authRegister' => 'Registrarse',
    'authRecovery' => 'Recuperar cuenta',
    'authReset' => 'Restablecer contraseña',
];
$authTitle = $authTitles[(string)($routeParams['view'] ?? '')] ?? 'Acceder';

return [
    'metaTags' => [
        'title' => $authTitle . ' - ' . site_name,
        'description' => $authTitle . ' en ' . site_name . '.',
        'robots' => 'noindex, nofollow',
    ],
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/vendors/external/sweetalert2/sweetalert2.min.css',
        'public/css/variables.css',
        'public/css/bootstrap-buttons-compat.css',
        'public/css/common.css',
        'public/vendors/external/sweetalert2/sweetTheme.css',
        'public/vendors/internal/gframe-icons/style.css',
        'public/vendors/internal/gframe-alerts/alertToast.css',
        'public/css/modules/auth/auth.css',
        'public/vendors/internal/passwordUtils/passwordUtils.css',
    ],
    'js' => [
        'public/vendors/external/jquery/jquery.min.js',
        'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
        'public/vendors/external/sweetalert2/sweetalert2.all.min.js',
        'public/vendors/internal/gframe-alerts/alertToast.js',
        'public/js/core/utils/forms.js',
        'public/js/core/utils/errors.js',
        'public/vendors/internal/passwordUtils/passwordUtils.js',
        ...($authScript !== null ? ['public/js/modules/auth/' . $authScript] : []),
    ],
];
