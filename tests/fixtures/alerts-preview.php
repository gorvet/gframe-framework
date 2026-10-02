<?php
// Revisión local aislada de componentes frontend; no modifica proyectos instalados.
if (PHP_SAPI !== 'cli-server') exit;
$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/resources/')) return false;
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Revisión de Alerts</title>
<link rel="stylesheet" href="/resources/modules/bootstrap/public/css/bootstrap.min.css">
<link rel="stylesheet" href="/resources/skeleton/public/css/variables.css">
<link rel="stylesheet" href="/resources/skeleton/public/css/bootstrap-buttons-compat.css">
<link rel="stylesheet" href="/resources/modules/gframe-icons/public/style.css">
<link rel="stylesheet" href="/resources/modules/sweetalert2/public/sweetalert2.min.css">
<link rel="stylesheet" href="/resources/modules/sweetalert2/public/sweetTheme.css">
<link rel="stylesheet" href="/resources/modules/alerts/public/alertToast.css">
</head>
<body class="p-4">
<h1>Revisión de Alerts</h1>
<div class="d-flex flex-wrap gap-2">
<button class="btn btn-secondary" id="theme">Cambiar tema</button>
<button class="btn btn-primary" id="confirm">Confirmación</button>
<button class="btn btn-primary" id="input">Campo y validación</button>
<button class="btn btn-primary" id="toast">Toast</button>
<button class="btn btn-primary" id="spinner">Carga</button>
</div>
<div id="toastBox"></div>
<script src="/resources/modules/jquery/public/jquery.min.js"></script>
<script src="/resources/modules/bootstrap/public/js/bootstrap.bundle.min.js"></script>
<script src="/resources/modules/sweetalert2/public/sweetalert2.all.min.js"></script>
<script src="/resources/modules/alerts/public/alertToast.js"></script>
<script>
document.getElementById('theme').onclick = () => {
  document.documentElement.dataset.bsTheme = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
};
document.getElementById('confirm').onclick = () => swalAlert({title: 'Confirmar operación', text: 'Mensaje de prueba sin efectos externos.', icon: 'warning', showCancelButton: true, cancelButtonText: 'Cancelar', confirmButtonText: 'Continuar'});
document.getElementById('input').onclick = () => swalAlert({title: 'Dato de prueba', input: 'text', inputPlaceholder: 'Escribe un valor', showCancelButton: true, cancelButtonText: 'Cancelar', confirmButtonText: 'Guardar', inputValidator: value => value ? undefined : 'Escribe un valor.'});
document.getElementById('toast').onclick = () => alertToast({title: 'Operación de prueba completada.', icon: 'success', timer: 12000});
document.getElementById('spinner').onclick = () => {showSpinner(true); setTimeout(() => showSpinner(false), 12000);};
</script>
</body></html>
