# Puentes visuales

Las bibliotecas externas se conservan intactas. Sus puentes pertenecen al módulo y utilizan `variables.css`, `common.css` y `data-bs-theme`; no hay otro controlador de tema. Se publican con el módulo y se cargan mediante los metadatos de las vistas que los necesitan.

## Orden de carga

Cargue primero el CSS original y después su puente. Mantenga Bootstrap, variables y common en el meta global. No cargue simultáneamente distintos temas de una misma biblioteca.

| Módulo | Puente publicado, relativo a public |
| --- | --- |
| GFSelect | `vendors/internal/gfselect/gf-select.css`, personalización de Dane |
| GFTable | Indicadores en `css/common.css`; usa tablas de Bootstrap |
| Flatpickr | `vendors/external/flatpickr/gframe-flatpickr.css` |
| Coloris | `vendors/external/coloris/gframe-coloris.css` |
| intl-tel-input | `vendors/external/intlTelInput/css/gframe-intl-tel-input.css` |
| TinyMCE | `vendors/external/tinymce/gframe-tinymce.css`, incluido en el meta de rich-text-editor |
| Swiper | `vendors/external/swiper/gframe-swiper.css` |
| Owl Carousel | `vendors/external/owl.carousel/assets/gframe-owl-carousel.css` |
| Venobox | `vendors/external/venobox/gframe-venobox.css` |
| jQuery UI | `vendors/external/jquery-ui/gframe-jquery-ui.css` |
| AOS | `vendors/external/aos/gframe-aos.css`, respeta movimiento reducido |
| ChartJS | `vendors/external/chartjs/gframe-chartjs.js`, después del motor |

SweetAlert2 conserva `sweetTheme.css`, integrado con Alerts. Los iconos heredan `currentColor`. PureCounter hereda la tipografía del elemento. html2canvas captura los estilos del DOM; jQuery, Luxon y Markdown no tienen controles visuales propios que requieran un tema. El contenido WordPress tiene su puente `wordpress-theme.css`; la integración de datos se revisa por separado.

## Integración y personalización

Cada vista inicializa las bibliotecas con sus opciones y elementos. Los puentes no crean calendarios, selectores, galerías ni gráficas automáticamente. Dentro de modales, use los contenedores previstos por las bibliotecas: `appendTo` en Flatpickr, `parent` en Coloris y `dropdownContainer` en teléfono. Mantenga los overlays dentro del modal para respetar su bloqueo de foco.

Coloris conserva los colores reales de muestras y gradientes. Venobox mantiene un telón oscuro para las imágenes. Swiper/Owl conservan contenido, tamaños e inicialización originales. jQuery UI conserva su calendario heredado, sin sustituir Flatpickr en formularios actuales.

## Gráficas

```js
const chart = new Chart(canvas, config);
const stopTheme = GFrameChartTheme.watch(chart);
// Antes de retirar el componente:
stopTheme();
chart.destroy();
```

`options()` devuelve opciones del tema actual; `apply(chart)` aplica texto, ejes, cuadrícula, leyenda y tooltip una vez. `watch(chart)` actualiza esos elementos al cambiar el tema y devuelve la función que desconecta la observación. No cambia datasets, colores de series, datos, formatos ni callbacks. Es optativo para gráficas con una presentación propia.

## Editor

El puente adapta la interfaz de TinyMCE; rich-text-editor sincroniza variables dentro del iframe y desconecta el observador al destruirlo. No modifica el HTML guardado ni la normalización de Word. TinyMCE utilizado sin el componente necesita que la aplicación gestione el tema de su propio iframe.

## Comprobaciones

Laboratorio aislado, sin demos ni datos reales:

```powershell
php -S 127.0.0.1:8767 -t . tests/fixtures/visual-packages-preview.php
node --test tests/js/*.test.cjs
php packages/bin/phpunit --filter ModuleCatalogTest
```

Permite revisar ambos temas, búsqueda/ordenación, fecha y hora, teléfono, colores, editor, visor y carruseles. Las pruebas de publicación comprueban que los puentes se incluyan al instalar. Las opciones particulares y plugins adicionales de cada proyecto necesitan sus propias pruebas.
