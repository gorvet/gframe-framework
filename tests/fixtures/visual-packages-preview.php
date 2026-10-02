<?php
// Laboratorio local: no utiliza datos, vistas ni configuración de los demos.
if (PHP_SAPI !== 'cli-server') exit;
$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/resources/')) return false;
$css = ['bootstrap/public/css/bootstrap.min.css', 'flatpickr/public/flatpickr.min.css',
 'flatpickr/public/gframe-flatpickr.css', 'coloris/public/coloris.min.css', 'coloris/public/gframe-coloris.css',
 'intl-tel-input/public/css/intlTelInput.min.css', 'intl-tel-input/public/css/gframe-intl-tel-input.css',
 'swiper/public/swiper-bundle.min.css', 'swiper/public/gframe-swiper.css',
 'owl-carousel/public/assets/owl.carousel.min.css', 'owl-carousel/public/assets/owl.theme.default.min.css',
 'owl-carousel/public/assets/gframe-owl-carousel.css', 'venobox/public/venobox.min.css', 'venobox/public/gframe-venobox.css',
 'jquery-ui/public/jquery-ui.css', 'jquery-ui/public/gframe-jquery-ui.css', 'tinymce/public/gframe-tinymce.css'];
?>
<!doctype html><html lang="es" data-bs-theme="light"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Paquetes visuales</title>
<?php foreach ($css as $file): ?><link rel="stylesheet" href="/resources/modules/<?= $file ?>"><?php endforeach; ?>
<link rel="stylesheet" href="/resources/skeleton/public/css/variables.css">
<link rel="stylesheet" href="/resources/skeleton/public/css/common.css">
</head><body class="p-3"><main class="container">
<h1>Paquetes visuales</h1><button id="theme" class="btn btn-secondary mb-4">Cambiar tema</button>
<div class="row g-4">
<div class="col-md-4"><label for="date" class="form-label">Fecha y hora</label><input id="date" class="form-control"></div>
<div class="col-md-4"><label for="color" class="form-label">Color</label><input id="color" value="#5333ff" class="form-control" data-coloris></div>
<div class="col-md-4"><label for="phone" class="form-label">Teléfono</label><input id="phone" type="tel" class="form-control"></div>
<div class="col-md-6"><h2 class="fs-5">Tabla</h2><div class="table-responsive"><input class="form-control gf-search mb-2" aria-label="Buscar en tabla"><table class="table gf-table"><thead><tr><th class="sortable">Nombre</th><th class="sortable" data-type="number">Cantidad</th></tr></thead><tbody><tr><td>Cuba</td><td>3</td></tr><tr><td>España</td><td>1</td></tr></tbody></table></div></div>
<div class="col-md-6"><h2 class="fs-5">Gráfica</h2><canvas id="chart" aria-label="Gráfica de prueba" role="img"></canvas></div>
<div class="col-md-6"><h2 class="fs-5">Swiper</h2><div class="swiper"><div class="swiper-wrapper"><div class="swiper-slide p-5">Primera diapositiva</div><div class="swiper-slide p-5">Segunda diapositiva</div></div><div class="swiper-pagination"></div><div class="swiper-button-prev"></div><div class="swiper-button-next"></div></div></div>
<div class="col-md-6"><h2 class="fs-5">Owl Carousel</h2><div class="owl-carousel owl-theme"><div class="p-5">Primera diapositiva</div><div class="p-5">Segunda diapositiva</div></div></div>
<div class="col-md-6"><h2 class="fs-5">jQuery UI</h2><label for="legacyDate" class="form-label">Calendario heredado</label><input id="legacyDate" class="form-control"><div id="slider" class="mt-3" aria-label="Valor"></div></div>
<div class="col-md-6"><h2 class="fs-5">Venobox</h2><a class="venobox btn btn-outline-primary" data-vbtype="inline" href="#lightbox">Abrir visor</a><div id="lightbox" style="display:none"><div class="p-4 bg-body text-body">Contenido de prueba del visor.</div></div></div>
<div class="col-12"><label for="content" class="form-label">Editor</label><textarea id="content" class="js-rich-text-editor" data-editor-min-height="320"><h2>Contenido de prueba</h2><p>Texto editable.</p><table><tbody><tr><td>Celda</td><td>Otra celda</td></tr></tbody></table></textarea></div>
</div></main>
<?php foreach (['jquery/public/jquery.min.js', 'jquery-ui/public/jquery-ui.min.js', 'flatpickr/public/flatpickr.js',
 'flatpickr/public/flatpickr_es.js', 'coloris/public/coloris.min.js', 'intl-tel-input/public/js/intlTelInputWithUtils.min.js',
 'gf-table/public/gf-table.js', 'chartjs/public/chart.umd.min.js', 'chartjs/public/gframe-chartjs.js',
 'swiper/public/swiper-bundle.min.js', 'owl-carousel/public/owl.carousel.min.js', 'venobox/public/venobox.min.js',
 'tinymce/public/tinymce.min.js'] as $file): ?><script src="/resources/modules/<?= $file ?>"></script><?php endforeach; ?>
<script>window.site_url='/';</script>
<script src="/resources/modules/rich-text-editor/public/rich-text-editor.js"></script>
<script>
document.getElementById('theme').onclick = () => document.documentElement.dataset.bsTheme = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
flatpickr('#date', {enableTime:true, locale:'es', defaultDate:'2026-10-01 12:00'});
Coloris({el:'#color', theme:'polaroid', formatToggle:true, clearButton:true, closeButton:true});
intlTelInput(document.getElementById('phone'), {initialCountry:'es', separateDialCode:true});
new Swiper('.swiper', {pagination:{el:'.swiper-pagination',clickable:true}, navigation:{nextEl:'.swiper-button-next',prevEl:'.swiper-button-prev'}});
$('.owl-carousel').owlCarousel({items:1, nav:true, dots:true});
$('#legacyDate').datepicker(); $('#slider').slider(); new VenoBox({selector:'.venobox'});
var previewChart = new Chart(document.getElementById('chart'), {type:'bar', data:{labels:['Uno','Dos'],datasets:[{label:'Prueba',data:[3,7],backgroundColor:'#5333ff'}]},options:{animation:false}});
GFrameChartTheme.watch(previewChart);
AdminRichTextEditor.register('content', {language:'en',language_url:undefined,min_height:320});
</script></body></html>
