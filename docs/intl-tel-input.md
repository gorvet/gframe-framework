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
