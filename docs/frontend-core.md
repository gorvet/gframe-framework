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

## Paginación

`creaPaginacion(total_pages, page)` genera los controles de `#all_items_pagination`. Define `window.fetchDataForPage(page)` en tu vista para solicitar la página y actualizar el contenedor con el HTML del servidor. Los eventos de navegación se limitan a ese contenedor. Esta utilidad no consulta datos ni impone sincronización con la URL.

## Helpers y extensión

`getNewURL` modifica segmentos de una ruta; `stringToURL` prepara un slug; `createAutoSlug` coordina su generación y respeta la edición manual; `randomNameGen` genera nombres aleatorios, no tokens de seguridad. Consulta las firmas en `utils/helpers.js` antes de usarlos.

Añade comportamientos específicos en el JS de tu aplicación y cárgalos mediante meta. No edites estos archivos publicados: una actualización del framework puede reemplazarlos. Los cambios reutilizables del contrato deben realizarse en el paquete y documentarse antes de actualizar las aplicaciones.
