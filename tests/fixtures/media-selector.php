<?php
// Servidor temporal exclusivo de pruebas; no usa aplicaciones instaladas ni credenciales reales.
$root = getenv('GFRAME_MEDIA_TEST_ROOT');
if (!$root || !is_dir($root)) { http_response_code(503); exit; }
$framework = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/jquery.js' => 'jquery/public/jquery.min.js', '/bootstrap.js' => 'bootstrap/public/js/bootstrap.bundle.min.js',
    '/bootstrap.css' => 'bootstrap/public/css/bootstrap.min.css', '/media.css' => 'media-library/public/media-library.css',
    '/library.js' => 'media-library/javascript/media-library.js', '/picker.js' => 'media-library/javascript/media-picker.js',
    '/field.js' => 'media-library/javascript/media-field.js',
    '/admin.js' => 'media-library/javascript/media-admin.js',
    '/rich-editor.js' => 'rich-text-editor/public/rich-text-editor.js',
    '/public/css/modules/media-library/media-content.css' => 'media-library/public/media-content.css',
];
if (isset($assets[$path])) {
    header('Content-Type: ' . (str_ends_with($path, '.css') ? 'text/css' : 'application/javascript'));
    readfile($framework . '/resources/modules/' . $assets[$path]); exit;
}
$tinyPrefix = '/public/vendors/external/tinymce/';
if (str_starts_with($path, $tinyPrefix)) {
    $tinyRoot = realpath($framework . '/resources/modules/tinymce/public');
    $file = realpath($tinyRoot . '/' . substr($path, strlen($tinyPrefix)));
    if (!$file || !str_starts_with($file, $tinyRoot . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : (str_ends_with($file, '.js') ? 'application/javascript' : mime_content_type($file))));
    readfile($file); exit;
}
require $framework . '/packages/autoload.php';
define('ABSPATH', $root . '/');
define('site_url', getenv('GFRAME_MEDIA_TEST_URL') ?: 'http://127.0.0.1:' . (int)$_SERVER['SERVER_PORT'] . '/');
define('DB_DEFAULT_CONNECTION', 'media_selector');
define('DB_CONNECTIONS', ['media_selector' => ['driver' => 'sqlite', 'path' => $root . '/media.sqlite']]);
\GFrame\Config\ConfigRepository::replace(['media' => ['scope' => 'global']]);
\GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['media-library', 'rich-text-editor'], $root);
$pdo = \DatabaseManager::connection();
if (!$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='media'")->fetchColumn()) {
    $pdo->exec(file_get_contents($framework . '/resources/modules/media-library/database/sqlite.sql'));
    mkdir($root . '/public', 0775, true);
    $image = $root . '/fixture.png';
    $testImage = getenv('GFRAME_MEDIA_TEST_IMAGE');
    if ($testImage && is_file($testImage)) copy($testImage, $image);
    else file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    $service = new \GFrame\Media\MediaLibraryService(new \GFrame\Media\MediaModel(), new \GFrame\Media\MediaStorage($root . '/public'));
    $service->registerLocalFile($image, 'Primera.png');
    $service->registerLocalFile($image, 'Segunda.png');
}
$_SESSION = ['auth' => ['id' => 1, 'name' => 'Pruebas']];
if ($path === '/admin' && !$pdo->query("SELECT COUNT(*) FROM media WHERE source='navigation'")->fetchColumn()) {
    $insert = $pdo->prepare("INSERT INTO media (source, kind, name, original_name, path, mime_type, size_bytes, created_at) VALUES ('navigation', 'docs', ?, ?, ?, 'text/plain', ?, '2026-10-15 00:00:00')");
    for ($i = 1; $i <= 26; ++$i) {
        $name = sprintf('Documento %02d', $i);
        $insert->execute([$name . '.txt', $name, 'uploads/doc-' . $i . '.txt', $i * 1024]);
    }
}
$controller = new \GFrame\Modules\MediaLibrary\Controllers\MediaController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if ($path === '/content') {
        $html = \GFrame\Security\HtmlSanitizer::sanitize((string)($_POST['content'] ?? ''));
        file_put_contents($root . '/content.html', $html);
        echo json_encode(['status' => 'success', 'data' => ['html' => $html]]); exit;
    }
    $action = basename($path);
    if (!in_array($action, ['list', 'details', 'save', 'field', 'upload'], true)) { http_response_code(404); exit; }
    // Respuesta denegada para probar la UX; los permisos reales se comprueban en las rutas/tests PHP.
    if ($action === 'save' && is_file($root . '/deny-save')) { http_response_code(403); echo '{"status":"unauthorized","code":"permission","message":"No tienes permiso para editar."}'; exit; }
    echo json_encode($controller->$action()); exit;
}
if (str_starts_with($path, '/public/uploads/')) {
    $file = realpath($root . $path);
    $public = realpath($root . '/public/uploads');
    if ($file && $public && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file)) { header('Content-Type: ' . mime_content_type($file)); readfile($file); exit; }
    http_response_code(404); exit;
}
?>
<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/media.css"><title>Selector multimedia — pruebas</title>
<?php if ($path === '/editor'): ?><link rel="stylesheet" href="/public/vendors/external/tinymce/gframe-tinymce.css"><?php endif; ?>
<body class="p-3"><form id="tokens"><input name="csrfToken" value="synthetic-test-token"></form>
<?php if ($path === '/editor'): ?>
<form id="writing-form">
<label class="form-label" for="writing-title">Título</label><input class="form-control" id="writing-title" name="title" required>
<?php foreach (['writing-one', 'writing-two', 'writing-plain'] as $editorID):
    $richTextEditor = ['id' => $editorID, 'name' => $editorID, 'label' => $editorID, 'min_height' => 320,
        'media_library' => $editorID !== 'writing-plain',
        'save_button' => $editorID === 'writing-one' ? 'writing-save' : ($editorID === 'writing-two' ? 'other-save' : ''),
        'value' => $editorID === 'writing-one' ? (is_file($root . '/content.html') ? file_get_contents($root . '/content.html') : '<p>Antes después</p>') : '<p>Segundo</p>'];
    include $framework . '/resources/modules/rich-text-editor/application/app/views/rich-text-editor/richTextEditor.php';
endforeach; ?>
<button type="submit" id="writing-save" class="btn btn-primary">Guardar artículo</button>
</form>
<form id="other-writing-form"><button type="button" id="other-save">Guardar otro formulario</button></form>
<?php include $framework . '/resources/modules/media-library/application/app/views/media-library/mediaPickerModal.php'; ?>
<?php elseif ($path === '/admin'): ?>
<?php $data = $controller->index(); include $framework . '/resources/modules/media-library/application/app/views/media-library/mediaIndex.php'; ?>
<?php else: ?>
<form id="application-form">
<?php
$mediaField = ['name' => 'gallery', 'label' => 'Galería', 'value' => [1, 2], 'multiple' => true, 'accept' => 'images'];
include $framework . '/resources/modules/media-library/application/app/views/media-library/mediaField.php';
include $framework . '/resources/modules/media-library/application/app/views/media-library/mediaPickerModal.php';
?>
</form>
<?php endif; ?>
<script>var site_url = <?= json_encode(site_url) ?>; window.alertToast = function(data) { window.lastToast = data; };</script>
<script src="/jquery.js"></script><script src="/bootstrap.js"></script><script src="/library.js"></script><script src="/picker.js"></script><script src="/field.js"></script>
<?php if ($path === '/admin'): ?><script src="/admin.js"></script><?php endif; ?>
<?php if ($path === '/editor'): ?><script src="/public/vendors/external/tinymce/tinymce.min.js"></script><script src="/rich-editor.js"></script>
<script>
window.writingSaves = []; window.otherSaves = 0;
document.getElementById('writing-form').addEventListener('submit', function(event) {
  event.preventDefault(); window.writingSaves.push(document.getElementById('writing-one').value);
});
document.getElementById('other-save').addEventListener('click', function() { window.otherSaves++; });
</script><?php endif; ?>
</body></html>
