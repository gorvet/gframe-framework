# Frontend core

`frontend-core` es un módulo de interfaz que reúne las utilidades compartidas de formularios, errores, paginación y slugs. Se instala como parte de la base obligatoria y depende de jQuery. Su nombre no significa que incluya todos los componentes visuales: Alertas, Markdown y GF Table tienen módulos propios.

## Relación con el núcleo PHP

El catálogo no contiene un módulo denominado `backend-core`. El núcleo PHP es el conjunto de servicios del framework para arranque, rutas, middleware, renderizado, datos y otras funciones de servidor; se describe en [Arquitectura](arquitectura.md).

`frontend-core` publica JavaScript en `public/js/core/`, pero esa carpeta no es una categoría de módulos ni indica que todos sus archivos procedan de este módulo. Por ejemplo, `heartbeat.js` y `session.js` pertenecen a `heartbeat-client`.

| Archivo publicado por `frontend-core` | Responsabilidad |
| --- | --- |
| `utils.js` | Carga secuencial y aviso de disponibilidad |
| `utils/helpers.js` | URLs, slugs y nombres auxiliares |
| `utils/errors.js` | Interpretación de errores y listeners AJAX globales |
| `utils/forms.js` | Mensajes para restricciones de validación nativa |
| `utils/pagination.js` | Controles de paginación de un listado |

## Carga

Registra `public/js/core/utils.js` en el meta de las vistas que lo necesitan, después de jQuery. El cargador incorpora secuencialmente `helpers.js`, `errors.js`, `forms.js` y `pagination.js` desde su carpeta `utils/`.

```php
<?php

return [
    'js' => [
        'public/js/core/utils.js',
        'public/js/app/catalogo/list.js',
    ],
];
```

El ejemplo presupone jQuery cargado por la meta global. `list.js` debe utilizar el siguiente control de disponibilidad; aparecer después del cargador en la lista no basta para esperar sus archivos internos.

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
function validarFormulario(form) {
  if (!form.checkValidity()) {
    validationFeedback($(form), {
      email: { valueMissing: 'Introduce tu correo.', typeMismatch: 'Revisa el correo.' }
    });
    form.classList.add('was-validated');
    return false;
  }
  return true;
}
```

La vista debe usar `needs-validation`, `novalidate`, atributos HTML5 y un destino `.validation_email` para el campo `id="email"`. Si no necesitas mensajes personalizados, pasa `{}`. Los mensajes se insertan como HTML: utiliza textos definidos por la aplicación, nunca entrada sin sanear del usuario. Mantén los identificadores únicos en la página.

Por ejemplo, un campo de correo:

```html
<form id="miFormulario" class="needs-validation" novalidate>
  <label for="email" class="form-label">Correo</label>
  <input id="email" name="email" type="email" class="form-control" required>
  <div class="invalid-feedback validation_email">Introduce un correo válido.</div>
  <button type="submit" class="btn btn-primary">Guardar</button>
</form>
```

Use `minlength`, `maxlength`, `pattern`, `min`, `max` y `step` cuando corresponda al campo. El helper reconoce `valueMissing`, `tooShort`, `patternMismatch`, `typeMismatch`, `rangeUnderflow`, `rangeOverflow`, `stepMismatch`, `badInput` y `customError`, en ese orden. No asigna restricciones ni agrega validadores automáticamente. `tooLong` no tiene un mensaje personalizado en este helper.

El mapa usa el ID, no el atributo `name`. El destino `.validation_<id>` se busca en toda la página; evite IDs duplicados entre formularios o modales. El helper recorre únicamente campos inválidos y no limpia todos los mensajes anteriores al corregirlos. Mantenga un texto de respaldo en el elemento de feedback.

En el submit, cancele la navegación con `event.preventDefault()`, valide y envíe los datos por AJAX. Incluya los tokens CSRF del formulario o del header común; no copie ni invente sus valores. El módulo no intercepta todos los formularios ni envía datos automáticamente. Desactive temporalmente el botón mientras la solicitud esté pendiente y restáurelo tanto en éxito como en fallo.

El backend vuelve a validar los mismos requisitos, autorización y ámbito. Una respuesta `status: success` puede indicar que un trabajo quedó encolado, no que terminó su efecto externo; ajuste el mensaje a esa operación. Para feedback, utilice [Alertas](alerts.md), no un bloque improvisado fuera del formulario.

## Errores y fragmentos

`successError(message, code)` interpreta códigos compartidos; `ajaxError(status, error)` prepara información de fallos de transporte. Ambos devuelven un objeto para feedback, pero no asignan su icono. La vista debe usar `icon: 'error'` cuando corresponda y presentar el resultado mediante `alertToast` o `swalAlert`. Conserva los códigos exactos del backend.

`successError()` no es un manejador de respuestas exitosas pese a su nombre. Determinados códigos solicitan una página de error (`forbidden`, `not_found`, `service_unavailable` y sus alias) o recargan la página (`to_reload`, `to_login`). En esos casos devuelve un objeto vacío; no muestres además un toast vacío. Para errores de negocio con mensaje devuelve `{title: ...}`.

`toErrorView(errorType, tolink, infoMsg)` solicita la vista de error y sustituye el documento completo. El manejador AJAX global también sustituye el documento cuando recibe HTML con estado 200, salvo cuando `window.__gfAuthInactive` es verdadero. No comprueba que ese HTML sea realmente una página de error. Por eso los fragmentos deben viajar dentro del contrato JSON, no como HTML directo.

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
    const feedback = successError(response.message || '', response.code);
    if (feedback.title) alertToast({ ...feedback, icon: 'error' });
  }).fail(function (xhr, status, error) {
    alertToast({ ...ajaxError(status, error), icon: 'error' });
  });
}
```

Carga jQuery, las utilidades y Alertas mediante meta antes de ejecutar este código. `#tokens` procede del header compartido. La ruta AJAX mantiene las mismas comprobaciones de autenticación, permisos y CSRF que el resto del módulo.

Envía también los filtros activos al cambiar de página. El modelo devuelve la página válida y el total filtrado; el parcial solo muestra paginación cuando `meta.total_pages > 1`. Tras sustituir el HTML, reinicializa los componentes que lo necesiten o utiliza eventos delegados. En listados con estado en la URL, conserva búsqueda y página con `URLSearchParams`, History y `popstate`.

Este patrón actualiza fragmentos dentro de una pantalla. Las visitas a páginas, descargas, API y SSE conservan sus respectivos canales; no se convierten en AJAX por defecto.

### Helper de paginación

`creaPaginacion(total_pages, page)` genera los controles de `#all_items_pagination`. Define `window.fetchDataForPage(page)` en tu vista para solicitar la página y actualizar el contenedor con el HTML del servidor. Los eventos de navegación se limitan a ese contenedor. Esta utilidad no consulta datos ni impone sincronización con la URL.

El helper genera controles incluso con una sola página. Su vista debe ocultar o vaciar el contenedor cuando no se necesite; con cero páginas, la función retorna sin limpiar el HTML anterior. El callback global y el ID fijo sirven para un listado por pantalla. Para varios listados independientes, use controladores propios por contenedor.

## Helpers y extensión

`getNewURL` modifica segmentos de una ruta; `stringToURL` prepara un slug; `createAutoSlug` coordina su generación y respeta la edición manual; `randomNameGen` genera nombres aleatorios, no tokens de seguridad. Consulta las firmas en `utils/helpers.js` antes de usarlos.

| Helper | Contrato |
| --- | --- |
| `getNewURL(segment)` | Devuelve la parte de la URL actual anterior a la primera coincidencia exacta del segmento, sin query |
| `stringToURL(text, typing)` | Normaliza a minúsculas, sin tildes y con guiones; `typing: false` elimina guiones finales |
| `createAutoSlug(sourceSelector, slugSelector, options)` | Sincroniza dos inputs y devuelve `{reset}`; una edición manual bloquea la generación automática |
| `randomNameGen(prefix)` | Prefijo, guion bajo y seis caracteres pseudoaleatorios |

```js
const slug = stringToURL('Gestión de campañas', false);
const slugBinding = createAutoSlug('#title', '#slug');
```

Un slug existente queda bloqueado al inicializar, salvo si coincide con el patrón inicial `sin-titulo` o `sin-titulo-N`. `options.placeholderPattern` permite otro patrón. Tras cargar datos en un modal reutilizado, invoque `slugBinding.reset()` para recalcular el bloqueo; no modifica por sí mismo el valor ni elimina los listeners. Normalizar un slug no garantiza su unicidad: compruébela en backend.

Si el segmento no existe, `getNewURL()` corta usando el índice `-1`; no es un constructor general de URLs. Use `URL` o `URLSearchParams` para consultas y enlaces que no sigan ese contrato.

Añade comportamientos específicos en el JS de tu aplicación y cárgalos mediante meta. No edites estos archivos publicados: una actualización del framework puede reemplazarlos. Los cambios reutilizables del contrato deben realizarse en el paquete y documentarse antes de actualizar las aplicaciones.
