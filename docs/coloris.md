# Coloris

`coloris` es una librería externa opcional para seleccionar colores. GFrame la conserva sin modificar su código. Está registrada para publicarse en `public/vendors/external/coloris/`.

Para usarla, seleccione el módulo en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public coloris`. Incluya `coloris.min.css` y `coloris.min.js` desde esa carpeta en el meta de la vista que los necesita. La aplicación decide qué campos activan el selector y cómo guardar el color.

El manifiesto publica también `examples.html` como referencia de uso.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Campo e inicialización

Cargue `gframe-coloris.css` después del CSS original:

```html
<label for="brandColor">Color de marca</label>
<input id="brandColor" name="color" class="form-control" data-coloris value="#563dff">
```

```js
Coloris({ el: '[data-coloris]', format: 'hex' });
```

Guarde el valor del input y valide el formato en el servidor. El puente adapta el control al tema sin alterar los colores reales de muestras y gradientes. Dentro de un modal, configure `parent` con su contenedor para conservar el foco.

El manifiesto y la cabecera de los archivos no identifican una versión exacta; no se presupone que coincida con la versión actual del proveedor. [Documentación oficial](https://coloris.js.org/) y [código fuente](https://github.com/mdbassit/Coloris).
