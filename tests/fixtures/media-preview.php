<?php
// Servidor de revisión local con datos sintéticos; no usa proyectos instalados.
if (PHP_SAPI !== 'cli-server' || !getenv('GFRAME_MEDIA_QA_ROOT')) exit;
$project = rtrim((string)getenv('GFRAME_MEDIA_QA_ROOT'), '/\\');
$requestPath = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($requestPath, '/public/') && is_file($project . $requestPath)) return false;
$framework = dirname(__DIR__, 2);
require $framework . '/packages/autoload.php';
define('ABSPATH', $project . '/');
define('site_url', 'http://127.0.0.1:8766/');
define('site_name', 'Revisión de Multimedia');
define('DebugMode', false);
define('DB_DEFAULT_CONNECTION', 'media_preview');
define('DB_CONNECTIONS', ['media_preview' => ['driver' => 'sqlite', 'path' => $project . '/media.sqlite']]);
\GFrame\Config\ConfigRepository::replace(['media' => ['scope' => 'global', 'max_upload_bytes' => 26214400]]);
$catalog = \GFrame\Modules\ModuleCatalog::frameworkDefault();
if (!is_file($project . '/config/routes/routes_admin_dashboard.php')) {
    \GFrame\Install\ProjectScaffolder::frameworkDefault()->publish($project);
    (new \GFrame\Modules\ModuleAssetPublisher($catalog))->publishProject(['admin-panel', 'media-library'], $project);
}
\GFrame\Modules\ModuleRuntime::initialize($catalog, ['admin-panel', 'media-library'], $project);
$pdo = \DatabaseManager::connection();
if ($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='media'")->fetchColumn() == 0) {
    $pdo->exec((string)file_get_contents($framework . '/resources/database/schema/sqlite/auth.sql'));
    $pdo->exec((string)file_get_contents($framework . '/resources/modules/media-library/database/sqlite.sql'));
    $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('review@example.test', 'not-a-password', 1, 'verify')");
    $sample = $project . '/sample.txt';
    file_put_contents($sample, 'Archivo sintético para revisar Multimedia.');
    $media = new \GFrame\Media\MediaLibraryService(new \GFrame\Media\MediaModel(), new \GFrame\Media\MediaStorage($project . '/public'));
    $media->registerLocalFile($sample, 'Documento de prueba.txt');
    $image = imagecreatetruecolor(800, 1200);
    imagepng($image, $project . '/portrait.png');
    imagedestroy($image);
    $media->registerLocalFile($project . '/portrait.png', 'Retrato de prueba.png', 'library', null, ['id' => 1, 'name' => 'Revisión']);
}
$_SESSION = ['auth' => ['id' => 1, 'name' => 'Revisión', 'role_id' => 1, 'role' => 'superadministrator']];
$controller = new \GFrame\Modules\MediaLibrary\Controllers\MediaController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = basename($requestPath);
    if (!in_array($action, ['list', 'field', 'details', 'save', 'upload', 'delete', 'quota', 'sync', 'hotlink'], true)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($controller->$action());
    exit;
}
$routeParams = ['relativePath' => 'media-library', 'sourceModule' => 'media-library', 'view' => 'mediaIndex', 'templateName' => 'admin', 'currentURL' => site_url . 'admin/media'];
$render = new \Render();
$view = (new ReflectionClass(\Render::class))->getMethod('loadView');
$view->setAccessible(true);
$data = $controller->index();
$standalone = $requestPath === '/admin/media-form';
$nativeContent = $view->invoke($render, $routeParams, $data);
$content = $standalone ? '<div class="pagetitle"><h1>Formulario de prueba</h1></div>' : $nativeContent;
ob_start();
echo '<div class="card p-4 mt-4">';
$mediaField = ['name' => 'sample_media', 'label' => 'Selector reutilizable de prueba', 'value' => 1];
include \GFrame\Modules\ModuleRuntime::file('views', 'media-library/mediaField.php', 'media-library');
$mediaField = ['name' => 'sample_gallery', 'label' => 'Galería de prueba', 'value' => [1], 'multiple' => true, 'fragment' => 'gthumb'];
include \GFrame\Modules\ModuleRuntime::file('views', 'media-library/mediaField.php', 'media-library');
echo '</div>';
$content .= (string)ob_get_clean();
if ($standalone) {
    ob_start();
    include \GFrame\Modules\ModuleRuntime::file('views', 'media-library/mediaPickerModal.php', 'media-library');
    $content .= (string)ob_get_clean();
}
$render->loadTemplate($content, $routeParams, $data);
