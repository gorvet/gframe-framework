# Coloris

`coloris` es una librería externa opcional para seleccionar colores. GFrame la conserva sin modificar su código. Está registrada para publicarse en `public/vendors/external/coloris/`.

Para usarla, seleccione el módulo en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public coloris`. Incluya `coloris.min.css` y `coloris.min.js` desde esa carpeta en el meta de la vista que los necesita. La aplicación decide qué campos activan el selector y cómo guardar el color.

El manifiesto publica también `examples.html` como referencia de uso.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.
