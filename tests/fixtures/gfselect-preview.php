<?php
// Prueba aislada del selector: no utiliza ni modifica proyectos instalados.
if (PHP_SAPI !== 'cli-server') exit;
if (str_starts_with((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/resources/')) return false;
?>
<!doctype html><html lang="es" data-bs-theme="light"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Revisión de GFSelect</title>
<link rel="stylesheet" href="/resources/modules/bootstrap/public/css/bootstrap.min.css">
<link rel="stylesheet" href="/resources/skeleton/public/css/variables.css">
<link rel="stylesheet" href="/resources/skeleton/public/css/common.css">
<link rel="stylesheet" href="/resources/modules/gfselect/public/gf-select.css">
</head><body class="p-4">
<main class="container"><h1>GFSelect</h1>
<div class="d-flex gap-2 mb-4"><button id="theme" class="btn btn-secondary">Cambiar tema</button>
<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#example">Abrir modal</button>
<button id="replace" class="btn btn-secondary">Reemplazar opciones</button></div>
<form id="form" novalidate><div class="row g-3">
<div class="col-md-6"><label for="native" class="form-label">Nativo</label><select id="native" class="form-select"><option>Cuba</option></select></div>
<div class="col-md-6"><label for="country" class="form-label">País</label><select id="country" required><option value="">Seleccionar</option><option value="cu">Cuba</option><option value="es">España</option><option disabled>Deshabilitado</option></select></div>
<div class="col-md-6"><label for="multi" class="form-label">Múltiple</label><select id="multi" multiple data-max-selections="2"><option>Cuba</option><option>España</option><option>México</option></select></div>
<div class="col-md-6"><label for="disabled" class="form-label">Deshabilitado</label><select id="disabled" disabled><option>Cuba</option></select></div>
</div><button class="btn btn-primary mt-3">Validar</button></form></main>
<div class="modal fade" id="example" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h2 class="modal-title fs-5">Selector dentro del modal</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
<div class="modal-body"><label for="modalSelect" class="form-label">País en modal</label><select id="modalSelect"><option>Cuba</option><option>España</option><option>México</option></select></div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>
<script src="/resources/modules/bootstrap/public/js/bootstrap.bundle.min.js"></script>
<script src="/resources/modules/gfselect/public/gf-select.js"></script>
<script>
['country', 'multi', 'disabled', 'modalSelect'].forEach(id => new GFSelect('#' + id, {loadStyles: false}));
document.getElementById('theme').onclick = () => document.documentElement.dataset.bsTheme = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
document.getElementById('form').onsubmit = event => {event.preventDefault(); event.currentTarget.classList.add('was-validated'); GFSelect.getInstance(document.getElementById('country')).refresh();};
document.getElementById('replace').onclick = () => {document.getElementById('country').replaceChildren(new Option('Argentina', 'ar'), new Option('Chile', 'cl'));};
</script></body></html>
