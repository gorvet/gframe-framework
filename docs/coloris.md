# Coloris

`coloris` es una librería externa opcional para seleccionar colores. GFrame la conserva sin modificar su código. Está registrada para publicarse en `public/vendors/external/coloris/`.

Para usarla, seleccione el módulo en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public coloris`. Incluya `coloris.min.css` y `coloris.min.js` desde esa carpeta en el meta de la vista que los necesita. La aplicación decide qué campos activan el selector y cómo guardar el color.

Los archivos principales de GFrame coinciden con los de Dane y Base Confías; Bebots tiene otra variante de `coloris.min.js`. El manifiesto también publica `examples.html` deliberadamente, como referencia de uso para quien instale el módulo.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.
