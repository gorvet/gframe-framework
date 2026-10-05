# Frontend core

Utilidades compartidas de formularios, errores, paginación y helpers. No incluye Markdown ni GFTable, que tienen módulos propios.

## Carga

Registra `public/js/core/utils.js` en el meta de las vistas que lo necesitan, después de jQuery. El cargador incorpora secuencialmente `helpers.js`, `errors.js`, `forms.js` y `pagination.js` desde su carpeta `utils/`.

El código que depende de estas utilidades debe comprobar `window.__gfUtilsReady` o esperar el evento `gfutilsready`. La inclusión del cargador no implica que sus archivos ya estén disponibles.

```js
function iniciarVista() {
  // Registrar aquí los eventos del módulo.
}
if (window.__gfUtilsReady) iniciarVista();
else document.addEventListener('gfutilsready', iniciarVista, { once: true });
document.addEventListener('gfutilserror', function (event) {
  console.error('No se pudieron cargar las utilidades:', event.detail.files);
});
```

Ante un fallo de carga continúa con los archivos restantes, mantiene `__gfUtilsReady` en `false` y emite `gfutilserror` con las rutas fallidas. También quedan en `window.__gfUtilsErrors`. No reintenta automáticamente. Estos eventos controlan la carga de recursos, no detectan todos los errores de ejecución dentro de un archivo. No reutilices el atributo interno `data-gf-utils-file` para scripts propios.

## Formularios

`validationFeedback($form, mensajes)` recibe un objeto jQuery y un mapa de mensajes por **id** del campo. Usa los estados de validación HTML5; no sustituye la validación del servidor.

```js
const form = document.getElementById('miFormulario');
if (!form.checkValidity()) {
  validationFeedback($(form), {
    email: { valueMissing: 'Introduce tu correo.', typeMismatch: 'Revisa el correo.' }
  });
  form.classList.add('was-validated');
  return;
}
```

La vista debe usar `needs-validation`, `novalidate`, atributos HTML5 y un destino `.validation_email` para el campo `id="email"`. Si no necesitas mensajes personalizados, pasa `{}`. Los mensajes se insertan como HTML: utiliza textos definidos por la aplicación, nunca entrada sin sanear del usuario. Mantén los identificadores únicos en la página.

## Errores y fragmentos

`successError(message, code)` interpreta códigos compartidos; `ajaxError(status, error)` prepara información de fallos de transporte. La vista decide cómo presentar el resultado mediante `alertToast` o `swalAlert`. Conserva los códigos exactos del backend.

`toErrorView(errorType, tolink, infoMsg)` solicita la vista de error y sustituye el documento completo. El manejador AJAX global también sustituye el documento cuando recibe HTML con estado 200, salvo la excepción de autenticación contemplada en el código. Este comportamiento permite convertir la página actual en una vista de error.

Para actualizar solo una lista o un fragmento, devuelve JSON con `html` y reemplaza explícitamente su contenedor desde el JS de la vista. No devuelvas ese fragmento como una respuesta HTML directa al manejador global.

## Listados por AJAX

La primera visita carga la página mediante una ruta web. Los filtros, la búsqueda, la paginación y las acciones posteriores se solicitan por AJAX y actualizan solo el fragmento afectado. El navegador no consulta el ORM directamente: envía parámetros al controlador, que valida la petición y solicita los datos al modelo.

El recorrido habitual es:

```text
evento de la vista → AJAX → middleware → controlador → modelo/ORM
                                           ↓
                                     parcial PHP
                                           ↓
JSON con html y meta → reemplazo del contenedor del listado
```

El controlador devuelve `status`, `html` y `meta`, además de `message`, `code` o `data` cuando corresponda. El HTML se genera en un parcial PHP mediante buffer de salida; JavaScript lo inserta, sin reconstruir filas ni tarjetas con plantillas propias.

En la vista, reserva el contenedor para el listado inicial y sus recargas:

```html
<div id="all_items"><!-- HTML del parcial renderizado por PHP --></div>
```

Por ejemplo, el módulo de campañas publica `ajax/admin/notifications/campaigns/list`. Con el módulo instalado y sus permisos configurados:

```js
function cargarCampanas(page) {
  return $.ajax({
    url: site_url + 'ajax/admin/notifications/campaigns/list',
    type: 'POST',
    dataType: 'json',
    data: $('#tokens').serialize() + '&page=' + encodeURIComponent(page)
  }).done(function (response) {
    if (response.status === 'success') {
      $('#all_items').html(response.html);
      return;
    }
    alertToast(successError(response.message || '', response.code));
  }).fail(function (xhr, status, error) {
    alertToast(ajaxError(status, error));
  });
}
```

Carga jQuery, las utilidades y Alertas mediante meta antes de ejecutar este código. `#tokens` procede del header compartido. La ruta AJAX mantiene las mismas comprobaciones de autenticación, permisos y CSRF que el resto del módulo.

Envía también los filtros activos al cambiar de página. El modelo devuelve la página válida y el total filtrado; el parcial solo muestra paginación cuando `meta.total_pages > 1`. Tras sustituir el HTML, reinicializa los componentes que lo necesiten o utiliza eventos delegados. En listados con estado en la URL, conserva búsqueda y página con `URLSearchParams`, History y `popstate`.

Este patrón actualiza fragmentos dentro de una pantalla. Las visitas a páginas, descargas, API y SSE conservan sus respectivos canales; no se convierten en AJAX por defecto.

### Helper de paginación

`creaPaginacion(total_pages, page)` genera los controles de `#all_items_pagination`. Define `window.fetchDataForPage(page)` en tu vista para solicitar la página y actualizar el contenedor con el HTML del servidor. Los eventos de navegación se limitan a ese contenedor. Esta utilidad no consulta datos ni impone sincronización con la URL.

## Helpers y extensión

`getNewURL` modifica segmentos de una ruta; `stringToURL` prepara un slug; `createAutoSlug` coordina su generación y respeta la edición manual; `randomNameGen` genera nombres aleatorios, no tokens de seguridad. Consulta las firmas en `utils/helpers.js` antes de usarlos.

Añade comportamientos específicos en el JS de tu aplicación y cárgalos mediante meta. No edites estos archivos publicados: una actualización del framework puede reemplazarlos. Los cambios reutilizables del contrato deben realizarse en el paquete y documentarse antes de actualizar las aplicaciones.
