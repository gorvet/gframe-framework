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


## Fechas, zonas horarias y backend

Flatpickr representa la interacción del usuario; no define por sí solo la política temporal de la aplicación. Distinga entre fecha civil, fecha y hora local y timestamp absoluto. Un valor como `2026-10-05 14:00` no contiene zona horaria.

Cuando el backend necesite un instante absoluto, envíe además la zona conocida de la aplicación o convierta explícitamente antes de persistir. Para conversiones complejas en el navegador puede utilizar [Luxon](luxon.md). No mezcle silenciosamente hora local, UTC y zona del servidor.

## Valores iniciales y actualización

Conserve la instancia devuelta por `flatpickr()` para actualizar o destruir el selector. Si cambia el valor desde JavaScript, utilice la API de la instancia en lugar de modificar únicamente el texto visible del input.

```js
picker.setDate('2026-10-05 09:30', true);
```

El segundo argumento solicita que se disparen los eventos asociados al cambio. Use ese comportamiento conscientemente si otros controles dependen de la fecha.

## Formularios dinámicos y modales

Inicialice el selector después de insertar un fragmento AJAX y ejecute `destroy()` antes de retirar el input. No inicialice dos veces el mismo campo.

Dentro de un modal, `appendTo` debe apuntar a un elemento apropiado del modal para evitar problemas de z-index y foco. Verifique también scroll y teclado en móvil.

## Validación y accesibilidad

El calendario no sustituye la validación del backend. Compruebe rango, obligatoriedad y reglas de negocio después de recibir el valor. Si la fecha tiene límites, mantenga coherentes los atributos del campo, las opciones de Flatpickr y la validación del servidor.

Conserve una etiqueta asociada al input y no haga que el único modo de introducir una fecha sea una interacción de puntero. Revise navegación con teclado y el formato que percibe el usuario.

## Diagnóstico

Si el selector no funciona, revise el orden `flatpickr.js` -> locale opcional -> script de la vista, la existencia del input, la inicialización única y el CSS/puente. Si la hora guardada aparece desplazada, el problema suele estar en la conversión de zona, no en el calendario visual.
