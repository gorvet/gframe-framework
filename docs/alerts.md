# Feedback de operaciones

`alerts` es un componente frontend JS/CSS: toast, confirmaciones y estados de carga. No tiene MVC PHP ni gestiona el inbox de `notifications` o los envíos de Campañas.

## Recursos y dependencias

El manifiesto publica `alertToast.js` y `alertToast.css` en `public/vendors/internal/gframe-alerts`. Requiere jQuery, Bootstrap, GFrame Icons y SweetAlert2. Los metadatos globales y del admin cargan los recursos; el template debe incluir `#toastBox` una sola vez.

SweetAlert2 conserva sus archivos externos originales. Su puente existente, `public/vendors/external/sweetalert2/sweetTheme.css`, utiliza las variables Bootstrap de GFrame para fondos, textos, bordes, campos, iconos y carga. Debe cargarse después del CSS original de SweetAlert2 y de las variables. El tema utiliza `data-bs-theme`; no se añade otro controlador de tema.

## Toast

Use un toast para feedback breve que no necesita una decisión. Use `swalAlert` para confirmaciones, mensajes que el usuario debe reconocer o formularios dentro de un diálogo. Ambos reciben opciones de presentación; el backend devuelve el contrato de negocio, no instrucciones JavaScript.

```js
alertToast({title: respuesta.message, icon: 'success', timer: 5000});
```

La duración predeterminada es 5000 ms. `timer` controla tanto la retirada como la barra de progreso; `0` deja el toast visible sin barra. Valores negativos o inválidos usan 5000 ms. Los iconos admitidos son `success`, `error`, `warning` e `info`. La función devuelve el objeto jQuery del toast; puede retirarse explícitamente con `.remove()`.

El título se muestra como texto, no como HTML ejecutable. Los toast se ubican en la esquina inferior derecha, como en el componente original. La antigua opción `position` no cambia esa ubicación. Los colores y la tipografía se resuelven mediante variables, también en oscuro; en móvil se limita el ancho al espacio disponible.

Su ancho es estable: hasta 350 px, limitado por la pantalla, sin variar con la longitud del mensaje. El contenido se ajusta dentro del toast. No hay botón de cierre ni pausa automática al pasar el puntero; si utiliza `timer: 0`, gestione su retirada desde la aplicación.

```js
const notice = alertToast({title: 'Operación en curso.', icon: 'info', timer: 0});
// Cuando termine la operación:
notice.remove();
```

El icono predeterminado de `alertToast()` es `success`; un icono desconocido se convierte en `info`. Pase explícitamente `error` al mostrar un fallo. Otras opciones de SweetAlert2 no se aplican a este toast: su API efectiva es `title`, `icon` y `timer`.

## Confirmación y validación

```js
async function confirmarOperacion(respuesta) {
const resultado = await swalAlert({
    title: 'Confirmar operación',
    text: respuesta.message,
    icon: 'warning',
    showCancelButton: true,
    cancelButtonText: 'Cancelar',
    confirmButtonText: 'Continuar',
});
if (resultado.isConfirmed) {
    // Ejecutar la operación autorizada.
}
return resultado.isConfirmed;
}
```

`swalAlert` devuelve la promesa original de SweetAlert2. Conserva sus opciones y callbacks, sin modificar el objeto recibido. Por defecto utiliza botones Bootstrap centrados y Cancelar antes de la acción principal. Esta alineación corresponde a SweetAlert; los formularios normales mantienen sus acciones a la derecha. Las clases personalizadas se combinan con las predeterminadas, no eliminan accidentalmente las de los otros botones. Una operación destructiva puede usar `customClass: {confirmButton: 'btn btn-danger'}`. No se normalizan códigos ni se interpretan respuestas de negocio.

Los mensajes simples usan `text`. La opción `html` de SweetAlert2 se reserva para contenido confiable y escapado por la aplicación; el puente no lo sanea. Campos, validación, foco y estados de carga conservan la API de la librería.

Por defecto, el diálogo no cierra al pulsar fuera ni con Escape (`allowOutsideClick: false`, `allowEscapeKey: false`). Si un diálogo informativo debe admitir esos cierres, declárelos en sus opciones. La confirmación visual no sustituye la autorización ni CSRF del endpoint que ejecutará la acción.

## Feedback de una respuesta AJAX

Tras recibir JSON del backend, compruebe `status` antes de mostrar el resultado. Por ejemplo, una operación aceptada:

```js
function mostrarResultado(response) {
    if (response.status === 'success') {
        return swalAlert({
            title: 'Solicitud recibida',
            text: response.message || 'Gracias. Hemos recibido tu solicitud.',
            icon: 'success'
        });
    }
    const feedback = successError(response.message || '', response.code);
    if (feedback.title) return swalAlert({title: 'No se pudo completar', text: feedback.title, icon: 'error'});
}
```

`successError()` pertenece a [frontend-core](frontend-core.md); ciertos códigos abren una vista de error o recargan la página, por lo que devuelve un objeto sin título. Evite abrir otro diálogo en esos casos. Los errores de transporte se preparan con `ajaxError(status, error)` y pueden mostrarse con icono `error`.

Si el backend encoló un correo o una tarea, comunique recepción o programación, no entrega confirmada. El módulo no interpreta `mail_queued`, no procesa colas ni espera el resultado de una tarea Async.

## Carga

`showSpinner(selector, texto, true/false)` controla un botón. `showSpinner(true)` y `showSpinner(false)` abren y cierran el indicador global. El puente mantiene visible el loader sin mostrar botones, título ni panel opaco.

```js
showSpinner('#saveButton', 'Guardando…', true);
// En el callback final de la solicitud, tanto si funcionó como si falló:
showSpinner('#saveButton', 'Guardar', false);
```

El spinner del botón modifica `disabled` y sustituye su HTML; no guarda el texto anterior. Proporcione el texto de restauración expresamente y no use contenido HTML procedente del visitante. Si el selector no encuentra un elemento, la función abre o cierra el spinner global: compruebe que el botón existe.

El spinner global utiliza un diálogo SweetAlert2 compartido. Cerrarlo con `showSpinner(false)` llama a `Swal.close()`, por lo que puede cerrar otro diálogo que se haya abierto mientras la tarea seguía pendiente. Termine el estado de carga antes de mostrar el resultado y coordine operaciones simultáneas desde la vista.

## Recursos y personalización de una vista

En un template propio, compruebe que existan los recursos de las dependencias y el contenedor:

```html
<div id="toastBox"></div>
```

No añada otro si el template ya lo incluye. Los tooltips Bootstrap presentes al cargar `alertToast.js` también se inicializan; los insertados después por AJAX necesitan su inicialización específica.

Mantenga las opciones de cada operación en el JavaScript de su vista. Para estilos del proyecto, use las variables comunes o un CSS propio cargado después del puente; no edite los assets publicados del módulo. `alerts` no ofrece una clase PHP que deba extenderse.


## Verificación

La suite JavaScript comprueba el contrato del componente:

```bash
node --test tests/js/alerts.test.cjs
```

Estas pruebas cubren duración, barra de progreso, tratamiento seguro del texto, combinación de clases, callbacks, inmutabilidad de opciones y reglas visuales del spinner.

Para revisión manual existe una fixture aislada:

```bash
php -S 127.0.0.1:8767 -t . tests/fixtures/alerts-preview.php
```

La fixture permite comprobar toast, diálogos y carga sin depender de datos reales de una aplicación. Úsela además de los tests cuando cambien estilos, foco, responsive, tema oscuro o interacción con SweetAlert2.
