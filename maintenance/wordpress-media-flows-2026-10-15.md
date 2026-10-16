# Biblioteca multimedia: investigación de flujos WordPress/GFrame

Revisión estática de código local y documentación oficial, 2026-10-15. No se verificó visualmente una instalación en navegador. Se investiga; no se implementa runtime. El usuario prioriza biblioteca, selección tras subir y edición de atributos dentro del selector; Gutenberg queda limitado a escritura.

Revisión posterior ampliada a todo el recorrido de la biblioteca: [inventario de 41 capacidades](wordpress-media-inventory-2026-10-15.md), con fuentes, límites, diferencias frente a plugins y prioridades propuestas. Esta nota inicial no limita la investigación a los ejemplos recordados por el usuario.

## Hallazgos comprobados

| Flujo | WordPress | GFrame actual |
|---|---|---|
| Subir desde selector | El controlador Library conecta la cola con la selección y cambia de subir a biblioteca; autoSelect está habilitado por defecto | attachUpload registra el éxito, resetea filtros y recarga; no transmite el nuevo ID a la selección del picker |
| Selección simple tras varias subidas | El comportamiento documentado en el código toma el último elemento de la cola | No hay selección automática; no se debe depender de cuál respuesta termine de última |
| Atributos dentro del selector | Attachment Details muestra metadatos y permite editar alt, título, leyenda y descripción según permisos/soporte | MediaPicker solo captura ml:itemClick para seleccionar; no muestra un editor de atributos |
| Edición en biblioteca principal | Detalles/editado de adjunto | media-admin.js abre editMediaModal y guarda alt_text; ese modal no forma parte del selector reusable |
| Nombre editable | WordPress distingue título editable y nombre de archivo informativo | Backend admite original_name y alt_text; el formulario administrativo solo envía alt_text y muestra el nombre como texto |
| Galería | Flujos para seleccionar, revisar, ordenar, añadir/quitar y configurar presentación | Campo múltiple con IDs ordenados y miniaturas; sin interfaz específica de composición/reordenación en los JS revisados |
| Guardar selección | Selección y contenido son operaciones distintas de subida | El picker devuelve IDs al confirmar; el archivo se sube antes de confirmar y cancelar no lo elimina |

Fuentes precisas GFrame: resources/modules/media-library/javascript/{media-library,media-picker,media-field,media-admin}.js; application/app/views/media-library/{mediaPickerModal,mediaEditModal}.php; application/app/controllers/media-library/MediaController.php; src/GFrame/Media/MediaLibraryService.php. El endpoint save conserva original_name existente cuando el formulario no lo envía; no es un campo editable visible por el hecho de que el servicio lo soporte.

Fuentes locales WordPress: wp-includes/js/media-views.js, Library.uploading, GalleryEdit y AttachmentDetails; wp-includes/media-template.php, plantillas attachment-details, attachment-details-two-column y gallery-settings. Library.uploading añade a selección durante la cola: no significa que un archivo fallido ya sea válido para insertar.

## Primera mejora propuesta: subir y seleccionar

Al completar correctamente una subida en el selector:

1. Mostrar biblioteca y llevar el foco al archivo subido, con confirmación visible.
2. En selección simple, dejar una única imagen seleccionada y habilitar Usar; al subir varias, definir orden de selección de forma determinista.
3. En selección múltiple, añadir los archivos válidos a la selección existente, respetar máximo y conservar el orden previsto aunque las respuestas lleguen desordenadas.
4. No cerrar ni insertar automáticamente: el usuario puede editar atributos y confirmar.
5. Los fallos no se seleccionan; si un archivo se subió pero excede la selección máxima, informar que está guardado en biblioteca pero no seleccionado.

La comunicación debe pertenecer a la instancia de biblioteca que hizo la subida, no depender de un evento global que afecte otro selector. Cerrar/reabrir durante una subida requiere evitar que una respuesta antigua modifique una selección nueva. Conservar filtros compatibles con el contexto, especialmente el tipo permitido; recargar sin criterio no garantiza que el archivo quede visible o seleccionado. Para altas desde URL, evaluar el mismo flujo explícitamente, sin confundir enlace externo con archivo descargado.

## Segunda mejora propuesta: detalles y atributos sin salir

Incluir un panel de detalles en el mismo selector, reutilizando detalles y guardado del backend. Elegir una imagen sigue seleccionándola; el panel muestra la imagen activa sin confundirla con todas las seleccionadas.

- Datos informativos: nombre físico, tipo, tamaño, dimensiones, fecha, autor y URL.
- Campos separados: nombre visible o título, texto alternativo; evaluar leyenda y descripción con esquema y migraciones si se aprueban.
- Guardado visible con estado de error/éxito, actualización de la selección en memoria, tarjetas y resultado devuelto al consumidor.
- Usuarios con media.view pueden consultar; editar exige media.edit y CSRF. La propiedad se resuelve en servidor.
- Definir qué pasa con cambios pendientes al cambiar imagen, cerrar o confirmar. Guardar metadatos modifica el archivo de biblioteca aunque luego se cancele su selección: no prometer que Cancelar revierte ambas operaciones.
- Evitar un segundo modal superpuesto; mantener foco, navegación de teclado y diseño móvil utilizables.

Importante: editar título/nombre visible no renombra el archivo físico. WordPress muestra el nombre físico como dato y permite editar el título; no debe atribuirse al core un renombrado seguro de archivos. Renombrar físicamente exige migrar URLs, variantes y referencias y queda como feature independiente.

## Tercera mejora propuesta: galería como experiencia completa

Una galería es más que activar multiple=true:

- Área con todas las imágenes elegidas, aunque procedan de páginas distintas de la biblioteca.
- Añadir/quitar sin borrar los archivos, selección persistente y reabrir una galería ya guardada.
- Reordenar con arrastre y controles equivalentes de teclado.
- Confirmar orden exacto y conservarlo al guardar, recargar y renderizar.
- Si se integra en escritura: insertar, volver a editar y actualizar esa galería dentro del artículo.
- Si se aprueban opciones visuales: columnas, recorte/proporción, tamaño y ampliación; pertenecen al uso de la galería, no a la imagen global.

GFrame ya tiene base de IDs y selección múltiple. Sin embargo, el comportamiento append del campo combina IDs anteriores con los nuevos y no aplica las eliminaciones/reordenaciones del selector como sustitución completa. Una edición de galería debe devolver su composición completa y aplicarla como reemplazo, conservando append para los consumidores que lo necesitan.

No se propone introducir álbumes globales, un nuevo ámbito por galería ni restaurar media_relations. Cada consumidor sigue guardando su ID o lista ordenada; metadatos de presentación y leyendas de uso requieren definir un contrato adicional. En WordPress, las galerías de contenido no equivalen necesariamente a entidades independientes en la biblioteca.

Texto alternativo, leyenda y título tienen funciones distintas. El alt global puede servir como valor inicial, pero una imagen puede necesitar una descripción contextual o alt vacío cuando es decorativa. Guardar HTML con un alt copiado no garantiza actualización retroactiva al cambiar el dato de biblioteca.

## Límites de la revisión y prueba posterior

Se confirmó código y cableado, no la interacción visual. Antes de implementar, concretar campos y persistencia de leyendas/presentación. La verificación funcional deberá cubrir: subida simple/múltiple; respuestas desordenadas; error parcial; máximo alcanzado; subida con filtros; selección entre páginas; cancelar/reabrir durante subida; permisos; editar sin perder selección; reordenación y reapertura; quitar frente a borrar; teclado y móvil. No ejecutar sobre un sitio real como fixture.

## Gutenberg: corrección de alcance

El interés es escritura: párrafos, encabezados, listas, citas, tablas, imágenes y galerías dentro del texto, accesos rápidos, navegación por estructura y modo concentrado. No constructor de páginas, plantillas del sitio, cabeceras/pies ni administración visual de diseño.

La evaluación posterior debe comparar estas tareas con TinyMCE. Primero terminar los contratos de medios/galería reutilizables para que el editor elegido pueda consumirlos. No se propone sustituir TinyMCE sin una prueba de escritura, guardado y reapertura con nuestro sanitizador.

## Fuentes oficiales

- [Inserción y panel de detalles](https://wordpress.org/documentation/article/inserting-images-into-posts-and-pages-classic/).
- [Biblioteca y atributos desde edición](https://learn.wordpress.org/lesson/using-the-media-library-2/).
- [Galería: orden, leyendas y presentación](https://wordpress.org/documentation/article/gallery-block/).
- [Edición de metadatos de medios](https://wordpress.org/documentation/article/edit-media/).

Orden recomendado para conciliar: subida y selección; panel de atributos; composición y reordenación de galería; integración con escritura. Son propuestas investigadas, no un listado de implementación aprobado.
