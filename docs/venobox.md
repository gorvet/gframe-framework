# VenoBox

Librería externa opcional para mostrar imágenes y contenido en una caja de luz. GFrame distribuye la versión 2.0.4 en `public/vendors/external/venobox/`.

Seleccione `venobox` en el instalador o publíquelo con `php bin/modules.php publish /ruta/del/proyecto/public venobox`. Cargue `venobox.min.css` y `venobox.min.js` en el meta de la vista. La aplicación decide qué enlaces abren la caja de luz y cómo inicializarla.

La distribución incluye el puente visual del módulo. Consulte [Puentes visuales](paquetes-visuales.md) para sus rutas, orden de carga, variables y personalización.

## Enlaces del visor

Cargue `gframe-venobox.css` después del CSS original:

```html
<a class="project-lightbox" href="public/img/example.jpg" data-gall="projectGallery">
  <img src="public/img/example.jpg" alt="Vista del proyecto">
</a>
```

```js
new VenoBox({ selector: '.project-lightbox' });
```

El ejemplo usa una URL relativa: en vistas con rutas anidadas, genere la URL con la base del proyecto. Los enlaces de un mismo `data-gall` forman una galería. Para enlaces insertados por AJAX, revise el ciclo de inicialización de la versión distribuida y evite registrar la misma instancia repetidamente sobre los mismos enlaces.

El puente conserva el telón oscuro del visor y adapta sus controles; no reemplaza las imágenes ni la biblioteca multimedia de GFrame. [Documentación y ejemplos oficiales](https://veno.es/venobox/). La copia incluida es **2.0.4**, no necesariamente la versión actual de la web del proveedor.
