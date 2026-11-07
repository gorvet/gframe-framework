<?php
// Fixture aislado: vista y controlador nativos, cuenta en memoria y assets publicados.
$root = getenv('GFRAME_ACCOUNT_TEST_ROOT');
if (!$root || !is_dir($root)) { http_response_code(503); exit; }
$framework = dirname(__DIR__, 2);
require $framework . '/packages/autoload.php';
if (PHP_SAPI === 'cli') {
    $published = (new \GFrame\Modules\ModuleAssetPublisher(\GFrame\Modules\ModuleCatalog::frameworkDefault()))
        ->publish(['bootstrap', 'password-utils', 'gframe-icons'], $root . '/public');
    echo json_encode($published['modules']); exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/account.js' => 'self-account/javascript/self-account.js',
    '/account.css' => 'self-account/public/self-account.css',
    '/admin.css' => 'admin-panel/public/admin.css',
];
if (isset($assets[$path])) {
    header('Content-Type: ' . (str_ends_with($path, '.js') ? 'application/javascript' : 'text/css'));
    readfile($framework . '/resources/modules/' . $assets[$path]); exit;
}
if (str_starts_with($path, '/public/')) {
    $file = realpath($root . $path);
    if (!$file || !str_starts_with($file, realpath($root . '/public') . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : (str_ends_with($file, '.js') ? 'application/javascript' : 'font/woff2')));
    readfile($file); exit;
}
define('site_name', 'Prueba de cuenta');
define('site_url', 'http://127.0.0.1:' . $_SERVER['SERVER_PORT'] . '/');
$_SESSION = ['auth' => ['id' => 2], 'csrfToken' => 'account-test-token', 'csrfTimestamp' => '1'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (($_POST['csrfToken'] ?? '') !== 'account-test-token') { http_response_code(403); echo '{}'; exit; }
    require $framework . '/tests/SelfAccountServiceTest.php';
    require $framework . '/resources/modules/self-account/application/app/controllers/self-account/SelfAccountController.php';
    $model = new \GFrame\Tests\InMemorySelfAccountModel();
    $service = new \GFrame\Auth\SelfAccountService($model);
    $controller = new \GFrame\Modules\SelfAccount\Controllers\SelfAccountController($service);
    $response = $controller->changePassword();
    if ($response['status'] === 'success') $response['test_password_changed'] = password_verify($_POST['new_password'], $model->accounts[2]['password']);
    echo json_encode($response); exit;
}
$data = ['data' => ['account' => ['role' => 'superadministrator', 'email' => 'prueba@example.test']]];
?>
<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/public/vendors/external/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="/public/vendors/internal/passwordUtils/passwordUtils.css">
<link rel="stylesheet" href="/public/vendors/internal/gframe-icons/style.css">
<link rel="stylesheet" href="/admin.css"><link rel="stylesheet" href="/account.css">
<body class="tpl-admin"><aside class="sidebar" id="sidebar"><ul class="sidebar-nav">
<li class="nav-heading">Gestión del sitio</li>
<li><a href="#" class="nav-link current"><i class="gicon-image" aria-hidden="true"></i><span>Portada de inicio</span></a></li>
<li><a href="#" class="nav-link"><i class="gicon-config" aria-hidden="true"></i><span>Cuenta y seguridad</span></a></li>
<li><ul class="nav-content"><li><a href="#">Datos de la cuenta</a></li></ul></li>
</ul></aside><main id="main" class="p-4">
<?php include $framework . '/resources/modules/self-account/application/app/views/self-account/self-accountIndex.php'; ?>
</main><script>var site_url=<?= json_encode(site_url) ?>; window.alertToast=data=>window.lastToast=data;</script>
<script src="/public/vendors/external/jquery/jquery.min.js"></script>
<script src="/public/vendors/internal/passwordUtils/passwordUtils.js"></script><script src="/account.js"></script>
