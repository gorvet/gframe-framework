<?php
// Prueba aislada del formulario original; los envíos están deshabilitados.
if (PHP_SAPI !== 'cli-server') exit;
if (str_starts_with((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/resources/')) return false;
require dirname(__DIR__, 2) . '/packages/autoload.php';
define('site_url', 'http://127.0.0.1:8767');
GFrame\Modules\ModuleRuntime::initialize(GFrame\Modules\ModuleCatalog::frameworkDefault(), ['notification-campaigns'], dirname(__DIR__, 2));
$directory = dirname(__DIR__, 2) . '/resources/modules/notification-campaigns/application/app/views/notification-campaigns/';
$group = require $directory . 'notification-campaigns.group.meta.php';
$form = require $directory . 'form.meta.php';
$scripts = array_values(array_unique(array_merge($group['js'] ?? [], $form['js'])));
$data = ['data' => [], 'meta' => []];
$assets = [
 'public/vendors/external/flatpickr/flatpickr.js' => '/resources/modules/flatpickr/public/flatpickr.js',
 'public/vendors/external/flatpickr/flatpickr_es.js' => '/resources/modules/flatpickr/public/flatpickr_es.js',
 'public/vendors/internal/gfselect/gf-select.js' => '/resources/modules/gfselect/public/gf-select.js',
 'public/js/modules/notification-campaigns/campaigns.js' => '/resources/modules/notification-campaigns/javascript/campaigns.js',
];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Prueba de placeholders</title>
<link rel="stylesheet" href="/resources/modules/bootstrap/public/css/bootstrap.min.css">
<link rel="stylesheet" href="/resources/skeleton/public/css/variables.css">
<link rel="stylesheet" href="/resources/skeleton/public/css/common.css">
<link rel="stylesheet" href="/resources/modules/flatpickr/public/flatpickr.min.css">
<link rel="stylesheet" href="/resources/modules/flatpickr/public/gframe-flatpickr.css">
<link rel="stylesheet" href="/resources/modules/gfselect/public/gf-select.css">
</head><body class="p-4"><main><?php include $directory . 'form.php'; ?></main>
<script src="/resources/modules/jquery/public/jquery.min.js"></script>
<script>document.querySelectorAll('[type="submit"], [data-test-campaign], [data-preview-audience]').forEach(button => button.disabled = true);</script>
<?php foreach ($scripts as $script): ?><script src="<?= $assets[$script] ?>"></script><?php endforeach; ?>
</body></html>
