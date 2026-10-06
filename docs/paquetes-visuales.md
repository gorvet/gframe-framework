# Puentes visuales

Las bibliotecas externas se conservan intactas. Sus puentes pertenecen al módulo y utilizan `variables.css`, `common.css` y `data-bs-theme`; no hay otro controlador de tema. Se publican con el módulo y se cargan mediante los metadatos de las vistas que los necesitan.

Las responsabilidades de las hojas compartidas se explican en [Variables, estilos comunes y botones](estilos-comunes.md).

## Versiones distribuidas

| Biblioteca | Versión de la copia incluida |
| --- | --- |
| Bootstrap | 5.3.8 |
| jQuery | 3.5.1 |
| jQuery UI | 1.13.2, identificada en la cabecera |
| SweetAlert2 | 11.10.0 |
| TinyMCE | 8.6.0 |
| Chart.js | 4.4.2 |
| Flatpickr | 4.6.13 |
| html2canvas | 1.4.0 |
| intl-tel-input | 24.7.0 |
| Luxon | 3.6.1, identificada en el JavaScript |
| Owl Carousel | 2.3.4 |
| PureCounter | 1.5.0 |
| Swiper | 11.2.10 |
| VenoBox | 2.0.4 |
| AOS | 3.0.0-beta.6, comprobada frente a la distribución oficial |
| Coloris | 0.25.0, comprobada frente a la etiqueta oficial |

Las versiones corresponden a los archivos del paquete, no a las versiones más recientes del proveedor. Cada guía enlaza su fuente oficial. Una actualización de biblioteca debe comprobar su API, los plugins dependientes y el puente visual; no sustituya archivos del proyecto por versiones de CDN sin esa revisión.

## Carga por metas

Cargue primero el CSS original y después su puente. Mantenga Bootstrap, variables y common en el meta global. No cargue simultáneamente distintos temas de una misma biblioteca.

| Módulo | Puente publicado, relativo a public |
| --- | --- |
| GFSelect | `vendors/internal/gf-select/gf-select.css`, integración con variables de GFrame |
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

## Fragmentos AJAX

Inicialice los componentes después de insertar su HTML. Antes de reemplazarlo, libere las instancias y observers que ofrezca su API. No suponga que volver a incluir un archivo JavaScript reconfigura los componentes existentes. Compruebe cada integración en ambos temas, dentro de modales cuando corresponda y con teclado; los puentes no sustituyen las reglas de accesibilidad ni la validación del backend.


## Orden de carga y comprobaciones

Como regla general, cargue primero la biblioteca original y después el puente de GFrame. Las variables y estilos comunes del proyecto deben existir antes de los puentes que las consumen, y el CSS específico de una vista debe quedar al final cuando necesite sobrescribir una decisión local.

```text
Bootstrap / biblioteca original
-> variables.css
-> bootstrap-buttons-compat.css / common.css
-> puente GFrame de la biblioteca
-> CSS del módulo
-> CSS de la vista o aplicación
```

Evite cargar dos temas o dos copias de la misma biblioteca. Una duplicación puede producir inicialización doble, eventos repetidos o estilos difíciles de diagnosticar.

El repositorio incluye un laboratorio visual aislado:

```bash
php -S 127.0.0.1:8767 -t . tests/fixtures/visual-packages-preview.php
```

La fixture permite revisar ambos temas y componentes como búsqueda/ordenación, fecha y hora, teléfono, color, editor, visor y carruseles sin depender de datos reales de una aplicación.

Complementa esa revisión con:

```bash
node --test tests/js/*.test.cjs
php packages/bin/phpunit --filter ModuleCatalogTest
```

Las pruebas automatizadas comprueban contratos y publicación; el laboratorio visual detecta problemas de foco, overlays, tamaños, contraste, tema y composición que no siempre aparecen en una aserción de DOM. Las opciones particulares y plugins adicionales de cada proyecto necesitan sus propias pruebas.
