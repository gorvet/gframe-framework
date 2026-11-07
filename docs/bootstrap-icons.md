# Bootstrap Icons

El módulo `bootstrap-icons` distribuye localmente Bootstrap Icons 1.13.2, bajo licencia MIT. Es una biblioteca separada de Bootstrap; no requiere JavaScript ni una conexión a un CDN.

`gframe-icons` la incluye como dependencia y mantiene los nombres `gicon-*` mediante un mapeo de compatibilidad. Las vistas existentes no necesitan renombrar sus clases. Los iconos multicolor conservan la fuente original.

Para usar directamente las clases `bi`, declare `public/vendors/external/bootstrap-icons/font/bootstrap-icons.css` en la meta de la vista y utilice, por ejemplo, `<i class="bi bi-person" aria-hidden="true"></i>`. Los botones que solo muestran un icono necesitan un nombre accesible.

Fuentes oficiales: [catálogo y documentación](https://icons.getbootstrap.com/) y [repositorio](https://github.com/twbs/icons). La distribución conserva su licencia y el registro de origen en el módulo.
