# Transición a GFrame 3.0.0

Esta versión retira la API de relaciones genéricas del módulo de medios. La biblioteca, las subidas, el selector y los campos simples y múltiples conservan su funcionamiento.

## Compatibilidad

Se eliminan `attach()`, `detach()` y `related()` de `MediaLibraryService`, `MediaModel` y `MediaRepository`. Los proyectos deben guardar el ID de imagen o el JSON ordenado de IDs de galería en sus propias tablas, como ya hacen los campos del módulo.

Si un proyecto utilizaba esa API, traslada sus asociaciones a los campos del contenido antes de actualizar. La migración elimina `media_relations` y sus filas; no modifica la tabla `media`, los archivos ni los campos de los CRUD. Conserva una copia de seguridad antes de aplicar la migración.

## Actualización con Composer

La etiqueta Git `v3.0.0` identifica la versión Composer `3.0.0`; el paquete no declara un campo `version`. Ajusta la restricción de la aplicación a `^3.0` y ejecuta en desarrollo:

```bash
composer update gorvet/gframe
composer gframe:update -- --dry-run --preserve-custom
composer gframe:update -- --preserve-custom
```

El actualizador aplica la migración de retirada para MySQL o SQLite. Las instalaciones nuevas crean únicamente la tabla de archivos del módulo. Prueba los campos y las galerías de la aplicación antes de desplegarla.

El módulo no registra dónde utiliza cada CRUD un archivo ni impide borrar archivos referenciados. Quitar una selección del contenido conserva el archivo en la biblioteca.
