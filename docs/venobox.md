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


## Contenido y galerías

El enlace sigue siendo un enlace real. Mantenga un `href` válido para que la imagen o recurso pueda abrirse aunque JavaScript falle. El thumbnail necesita un `alt` útil cuando comunica contenido; si es decorativo, aplique la política correspondiente de la vista.

Use `data-gall` para agrupar únicamente elementos que formen una secuencia lógica. No mezcle recursos de secciones distintas solo para reutilizar un selector.

## Rutas y biblioteca multimedia

Las URLs deben generarse con la base del proyecto y respetar permisos. Para archivos administrados por [Media Library](media-library.md), obtenga la URL autorizada desde el backend; VenoBox no concede acceso a un archivo privado ni valida su ámbito.

Una caja de luz es presentación. Las restricciones de descarga, visibilidad o tenant deben resolverse antes de entregar el enlace al navegador.

## Contenido dinámico

Si un fragmento AJAX añade nuevos enlaces, inicialice o actualice la integración según el ciclo de la instancia que utilice. Evite ejecutar repetidamente una inicialización global sobre enlaces que ya están registrados. Si la pantalla reemplaza por completo la galería, retire las referencias anteriores antes de crear la nueva instancia.

## Accesibilidad y experiencia

Compruebe teclado, cierre con controles visibles, foco al abrir/cerrar y texto alternativo de imágenes. No convierta información esencial en contenido disponible únicamente dentro de la caja de luz.

## Diagnóstico

Si el visor abre la URL como navegación normal, revise la carga de JavaScript y el selector. Si aparece sin estilos, compruebe CSS original y puente. Si la imagen falla solo en rutas anidadas, revise la URL generada. Si una galería mezcla elementos inesperados, compruebe los valores de `data-gall`.
