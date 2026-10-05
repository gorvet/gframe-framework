# jQuery UI

Librería externa opcional para interacciones basadas en jQuery. El módulo `jquery-ui` declara `jquery` como dependencia y publica sus archivos en `public/vendors/external/jquery-ui/`.

Seleccione `jquery-ui` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public jquery-ui`. Cargue `jquery-ui.css` y `jquery-ui.min.js` en el meta de la vista, después de jQuery. El paquete incluye `jquery.ui.touch-punch.min.js` para las pantallas que lo necesiten. La aplicación decide qué interacción inicializar.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Versión, puente y uso

La cabecera del archivo distribuido identifica **1.13.2**. Cargue `gframe-jquery-ui.css` después de `jquery-ui.css`. Touch Punch es una dependencia adicional para determinadas interacciones táctiles, no un sustituto de las pruebas con teclado y móvil.

```js
$('#sortableItems').sortable({ handle: '.drag-handle' });
// Antes de sustituir el fragmento:
// $('#sortableItems').sortable('destroy');
```

El ejemplo supone una lista `sortableItems` con elementos y asas `.drag-handle`. Reordenar el DOM no persiste el orden: envíelo mediante una ruta AJAX autorizada y protegida con CSRF. Para fechas en formularios nuevos utilice la integración de [Flatpickr](flatpickr.md), sin cargar dos calendarios sobre el mismo campo.

[Proyecto oficial](https://jqueryui.com/) y [API de widgets](https://api.jqueryui.com/). Consulte las opciones y el método `destroy` de cada widget.
