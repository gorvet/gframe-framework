# Flatpickr

Librería externa opcional para seleccionar fechas y horas. GFrame distribuye la versión 4.6.13 en `public/vendors/external/flatpickr/`.

Seleccione `flatpickr` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public flatpickr`. Cargue `flatpickr.min.css` y `flatpickr.js` en el meta de la vista. Si necesita español, cargue también `flatpickr_es.js`. La aplicación define los campos, el formato y la inicialización. Se incluyen estilos alternativos para que el proyecto elija el suyo.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Campo e inicialización

Cargue `gframe-flatpickr.css` después del CSS original. Para la configuración siguiente, cargue `flatpickr_es.js` después de `flatpickr.js` y use un input `id="scheduledDate"`:

```js
const picker = flatpickr('#scheduledDate', {
  locale: 'es',
  enableTime: true,
  dateFormat: 'Y-m-d H:i',
  time_24hr: true
});
// Antes de retirar el input:
// picker.destroy();
```

El valor expresa una fecha local sin zona horaria. La aplicación debe decidir la zona y convertirla al formato del backend; no presuponga que el selector entrega UTC. Dentro de un modal, configure `appendTo` con un elemento del modal. No cargue un tema alternativo adicional encima del puente.

[Documentación oficial](https://flatpickr.js.org/) y [opciones](https://flatpickr.js.org/options/), correspondientes a la API de la rama 4.
