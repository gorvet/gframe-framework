# Feedback de operaciones

`alerts` es un componente frontend JS/CSS: toast, confirmaciones y estados de carga. No tiene MVC PHP ni gestiona el inbox de `notifications` o los envíos de Campañas.

## Recursos y dependencias

El manifiesto publica `alertToast.js` y `alertToast.css` en `public/vendors/internal/gframe-alerts`. Requiere jQuery, Bootstrap, GFrame Icons y SweetAlert2. Los metadatos globales y del admin cargan los recursos; el template debe incluir `#toastBox` una sola vez.

SweetAlert2 conserva sus archivos externos originales. Su puente existente, `public/vendors/external/sweetalert2/sweetTheme.css`, utiliza las variables Bootstrap de GFrame para fondos, textos, bordes, campos, iconos y carga. Debe cargarse después del CSS original de SweetAlert2 y de las variables. El tema utiliza `data-bs-theme`; no se añade otro controlador de tema.

## Toast

```js
alertToast({title: respuesta.message, icon: 'success', timer: 5000});
```

La duración predeterminada es 5000 ms. `timer` controla tanto la retirada como la barra de progreso; `0` deja el toast visible sin barra. Valores negativos o inválidos usan 5000 ms. Los iconos admitidos son `success`, `error`, `warning` e `info`. La función devuelve el objeto jQuery del toast; puede retirarse explícitamente con `.remove()`.

El título se muestra como texto, no como HTML ejecutable. Los toast se ubican en la esquina inferior derecha, como en el componente original. La antigua opción `position` no cambia esa ubicación. Los colores y la tipografía se resuelven mediante variables, también en oscuro; en móvil se limita el ancho al espacio disponible.

## Confirmación y validación

```js
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
```

`swalAlert` devuelve la promesa original de SweetAlert2. Conserva sus opciones y callbacks, sin modificar el objeto recibido. Por defecto utiliza botones Bootstrap centrados y Cancelar antes de la acción principal. Esta alineación corresponde a SweetAlert; los formularios normales mantienen sus acciones a la derecha. Las clases personalizadas se combinan con las predeterminadas, no eliminan accidentalmente las de los otros botones. Una operación destructiva puede usar `customClass: {confirmButton: 'btn btn-danger'}`. No se normalizan códigos ni se interpretan respuestas de negocio.

Los mensajes simples usan `text`. La opción `html` de SweetAlert2 se reserva para contenido confiable y escapado por la aplicación; el puente no lo sanea. Campos, validación, foco y estados de carga conservan la API de la librería.

## Carga

`showSpinner(selector, texto, true/false)` controla un botón. `showSpinner(true)` y `showSpinner(false)` abren y cierran el indicador global. El puente mantiene visible el loader sin mostrar botones, título ni panel opaco.

## Verificación

`node --test tests/js/alerts.test.cjs` comprueba duración, progreso, texto seguro, mezcla de clases, callbacks, opciones inmutables y reglas visuales del spinner. La revisión visual aislada se ejecuta con `php -S 127.0.0.1:8767 -t . tests/fixtures/alerts-preview.php`; no utiliza un demo ni datos reales.
