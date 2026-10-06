# intl-tel-input

Librería externa opcional para campos telefónicos internacionales. GFrame distribuye la versión 24.7.0 con sus estilos, utilidades, traducciones e imágenes en `public/vendors/external/intlTelInput/`.

Seleccione `intl-tel-input` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public intl-tel-input`. Cargue `css/intlTelInput.min.css` y la variante JavaScript que necesite desde `js/`; `intlTelInputWithUtils.min.js` incluye las utilidades. La aplicación configura el campo y valida el número antes de guardarlo. No use la presentación visual como única validación del backend.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Campo e instancia

Cargue `css/gframe-intl-tel-input.css` después del CSS original y `js/intlTelInputWithUtils.min.js` antes del código de la vista. Use un input `type="tel"` con `id="contactPhone"`:

```js
const phone = window.intlTelInput(document.querySelector('#contactPhone'), {
  initialCountry: 'cu'
});
phone.promise.then(function () {
  // Al validar el formulario:
  // phone.isValidNumber();
  // phone.getNumber();
});
// Antes de retirar el input:
// phone.destroy();
```

Espere la inicialización antes de usar las utilidades. `getNumber()` permite obtener el formato internacional; envíe ese valor al backend y vuelva a validarlo allí. Dentro de un modal, use `dropdownContainer` con su contenedor. Conserve las imágenes publicadas y sus rutas relativas.

[Repositorio y documentación del proveedor](https://github.com/jackocnr/intl-tel-input). Consulte la API correspondiente a **24.7.0**, no ejemplos de una versión mayor sin verificar compatibilidad.


## Normalización y almacenamiento

Para persistencia, prefiera un formato canónico internacional cuando el negocio lo permita. `getNumber()` puede producir el número normalizado a partir de la selección de país y el valor introducido, pero la aplicación debe volver a validar ese resultado en el backend.

No almacene por separado código de país y número nacional salvo que su dominio lo necesite. Si lo hace, mantenga también una representación canónica o una regla clara para reconstruirla.

## Validación de formularios

La apariencia válida del control no implica que el número exista ni que pertenezca al usuario. Diferencie formato válido, número plausible y verificación real mediante SMS o llamada cuando esa comprobación sea necesaria.

Antes de enviar un formulario AJAX, obtenga el valor normalizado y colóquelo en el campo o payload que espera el backend. Si conserva también el texto original, no lo use como sustituto del valor normalizado.

## Fragmentos y ciclo de vida

Para inputs insertados mediante AJAX, cree la instancia después de insertar el HTML y llame a `destroy()` antes de retirar el control. Mantenga una referencia por input; no registre varias instancias sobre el mismo elemento.

Dentro de modales, configure `dropdownContainer` en un contenedor compatible y pruebe foco, scroll y teclado. La lista de países puede ser alta y debe seguir siendo utilizable en pantallas pequeñas.

## Recursos y actualización

La biblioteca depende de archivos auxiliares e imágenes publicados junto al módulo. No copie solamente el JavaScript. Al actualizar la versión, compruebe rutas, utilidades, API y CSS del puente antes de reemplazar archivos.

## Diagnóstico

Si no aparece la bandera o el desplegable, revise CSS, imágenes y rutas relativas. Si `isValidNumber()` o `getNumber()` no están disponibles, compruebe que cargó la variante con utilidades y espere `phone.promise`. Si el número guardado no coincide con el mostrado, revise cuándo normaliza el valor y qué campo envía realmente el formulario.
