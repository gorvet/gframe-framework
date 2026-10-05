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


## Widgets, estado y persistencia

jQuery UI modifica el DOM para representar widgets e interacciones. Mantenga una referencia clara al elemento inicializado y destruya el widget antes de sustituir su fragmento. El método concreto depende del widget, por ejemplo `sortable('destroy')` para Sortable.

Cambiar el orden, tamaño o posición en el navegador no persiste nada por sí mismo. Envíe el estado nuevo a un endpoint autorizado, valide todos los identificadores y aplique CSRF cuando la ruta sea AJAX.

## Contenido dinámico

Inicialice los widgets después de insertar el HTML. Use eventos con namespace para la lógica propia de la vista y retire esos listeners cuando el fragmento tenga un ciclo de vida repetido. No cargue otra copia de jQuery para intentar corregir un plugin que no aparece: jQuery UI debe registrarse sobre la misma instancia que utiliza GFrame.

## Accesibilidad y táctil

Touch Punch añade compatibilidad táctil a determinadas interacciones heredadas, pero no convierte automáticamente un patrón de arrastrar y soltar en una experiencia accesible. Ofrezca alternativas de teclado o controles explícitos cuando el orden o movimiento sea una operación funcional importante.

Use asas visibles para Sortable cuando mover toda la fila pueda entrar en conflicto con selección, scroll o enlaces.

## Tema y actualización

Cargue `gframe-jquery-ui.css` después del CSS original y coloque los ajustes de la aplicación después del puente. No edite los archivos del vendor. Al actualizar jQuery UI, verifique también la compatibilidad con la versión de jQuery distribuida y con Touch Punch si la pantalla depende de él.

## Diagnóstico

Si aparece `$(...).sortable is not a function`, revise que jQuery cargó antes de jQuery UI y que no existe una segunda copia posterior. Si el widget funciona con ratón pero no con táctil, confirme si esa interacción necesita Touch Punch. Si el orden vuelve al anterior al recargar, falta persistir el cambio en backend.
