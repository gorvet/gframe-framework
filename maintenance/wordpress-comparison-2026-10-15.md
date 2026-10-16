# WordPress y GFrame: mejoras que conviene evaluar

Revisión de código y documentación, 2026-10-15. No es una prueba visual ni una auditoría de seguridad. La copia local de WordPress declara 7.1 en wp-includes/version.php; se contrasta con documentación oficial. No se cambia runtime, no se instalan plugins y no se publica una versión.

Aclaración posterior del usuario: Gutenberg interesa exclusivamente para escritura, no para crear o administrar páginas completas. Esa aclaración sustituye la recomendación inicial de evaluar un constructor de páginas. La investigación ampliada de la biblioteca está en [wordpress-media-flows-2026-10-15.md](wordpress-media-flows-2026-10-15.md); las propuestas iniciales siguientes se conservan como historial, no como alcance aprobado.

Skills canónicas consultadas: gframe-orchestrator, gframe-framework-maintenance y gframe-media-module; referencias de ámbitos, campos multimedia y edición de contenido. Las recomendaciones son propuestas, no tareas aprobadas ni una conciliación cerrada.

## Lo que ya tenemos

- Biblioteca con búsquedas y filtros, subida, detalles, selección simple/múltiple y conservación de selección entre páginas.
- Propiedad global, por usuario o tenant, permisos y CSRF. El ámbito del listado no convierte un archivo público en privado.
- Original conservado y variantes configurables; soporte de procesamiento GD. Hay métodos para optimizar y recortar: no equivale a que exista una interfaz para esas operaciones.
- TinyMCE 8.6 integrado: formato, tablas, enlaces, imágenes, vista previa, limpieza de pegado desde Word y tema visual. El componente no implementa almacenamiento de artículos.
- Sanitizador HTML en backend, de uso explícito. No hay que reemplazarlo por confiar en lo que acepte un editor.

Fuentes GFrame: docs/media-library.md, docs/rich-text-editor.md, src/GFrame/Media/MediaLibraryService.php, MediaProcessor.php, MediaModel.php y resources/modules/rich-text-editor/public/rich-text-editor.js.

## Comparación de medios

| Capacidad | WordPress | GFrame revisado | Propuesta |
|---|---|---|---|
| Seleccionar/subir desde edición | Biblioteca integrada con edición | Biblioteca y TinyMCE separados; la integración base del editor no configura MediaPicker ni subida | Primera mejora: seleccionar y subir con los permisos y ámbito existentes desde el editor |
| Imagen adecuada al dispositivo | Genera marcado srcset/sizes con tamaños compatibles | Variantes disponibles; no se encontró generador equivalente en Media ni en el editor | Helper de renderizado por ID, dimensiones, srcset/sizes y carga apropiada; no mezclar recortes con proporciones diferentes |
| Edición visual de imagen | Recorte, rotación y otras operaciones de edición | Métodos de procesamiento, sin editor visual equivalente en el módulo revisado | Edición no destructiva, conservando original y previsualizando resultado; presupuesto de píxeles/memoria |
| Metadatos editoriales | Título, texto alternativo, leyenda y descripción | La edición nativa persiste original_name y alt_text | Separar claramente nombre, alt y leyenda, según las necesidades reales |
| Operaciones masivas | Selección y borrado masivo en biblioteca | La selección múltiple del picker no es gestión masiva; eliminación por media_id | Añadir gestión masiva con permisos, errores parciales y confirmación; no confundir quitar de una galería con borrar |
| Uso de archivos | Attachment y asociaciones no son inventario universal de todas las referencias | Cada CRUD guarda IDs; delete no consulta consumidores | Añadir proveedores de consulta de uso por módulo, sin reintroducir tabla genérica de relaciones; advertir cobertura incompleta |

El mayor riesgo funcional observado es borrar una imagen que aún se utiliza: delete elimina registro y archivos sin consultar contenido. No significa que WordPress resuelva de forma completa ese problema. Su estado de archivo adjunto/no adjunto tampoco prueba que una imagen se use o no en toda la web. No conviene implementar limpieza automática basada solo en ese dato.

Medios privados, sustitución manteniendo ID y detección de duplicados son mejoras adicionales posibles de GFrame; no se presentan como funciones nativas universales de WordPress. Las carpetas de biblioteca, la optimización avanzada y otras capacidades frecuentes en plugins deben separarse del core de WordPress al comparar.

## TinyMCE frente a Gutenberg

No son equivalentes: nuestro componente edita un fragmento HTML; Gutenberg proporciona una plataforma de edición por bloques y composición de contenido. Para formularios y artículos sencillos, conservar TinyMCE es una opción razonable. Para páginas con columnas, galerías, llamadas a la acción y secciones reutilizables, un editor de bloques opcional sí puede aportar valor.

| Mejora | Valor | Coste o condición |
|---|---|---|
| Biblioteca multimedia dentro de TinyMCE | Evita copiar URLs y reutiliza permisos y selección | Integrar selector y persistencia/renderizado del medio; validar guardado y reapertura |
| Recuperación de borradores | Evita pérdidas al recargar o cerrar | Distinguir recuperación local de autoguardado persistente; aislar usuario/tenant, caducidad y datos sensibles |
| Revisiones y restauración | Permite comparar y recuperar cambios guardados | Servicio opcional con retención y control de permisos; necesita un consumidor de contenido, no solo el textarea |
| Detección de edición simultánea | Evita sobrescribir cambios ajenos | Versión del registro y rechazo de actualizaciones obsoletas; bloqueo visual no sustituye comprobación backend |
| Bloques y patrones reutilizables | Facilita construir páginas manteniendo diseño | Módulo opcional, formato versionado, renderizado, estilos, migraciones y permisos de cada bloque |

Gutenberg se puede utilizar fuera de WordPress mediante sus paquetes, pero no es sustituir un JS por otro. Hace falta integrar estado, medios, guardado, estilos y compatibilidad. El sanitizador actual de GFrame elimina clases/estilos/IDs y no preserva sin más todo el marcado de Gutenberg: sería obligatorio definir otro contrato de contenido seguro y comprobar el ciclo guardar → abrir → renderizar. Tampoco se deben ejecutar bloques PHP arbitrarios elegidos por un visitante.

Recomendación: no reemplazar TinyMCE ahora. Primero conectar medios y añadir protección del trabajo editorial; evaluar luego un prototipo de editor de bloques para páginas, separado del componente de texto existente. No es necesario construir todo Gutenberg desde cero ni importar todo WordPress.

## Otras ideas aprovechables

1. **Estado del sistema**, inspirado en Site Health: PHP/extensiones, permisos de escritura, límites de subida, migraciones y conectividad de cron. GFrame ya tiene diagnóstico CLI del cron; extenderlo con proveedores de comprobación y salida administrativa sin secretos. Un fallo debe distinguir problema detectado de comprobación no disponible.
2. **Flujo editorial opcional**: borrador, revisión, publicación y programación usando CronTaskService. Estados y permisos del contenido pertenecen al módulo consumidor, no a todo modelo del ORM. No confundir esta propuesta con campañas o notificaciones existentes.
3. **Patrones de contenido**: llamadas a la acción, fichas, preguntas frecuentes y galerías aprobadas. Pueden comenzar como componentes controlados antes de introducir un constructor completo.
4. **Exportación/importación de contenido**: interesante cuando exista un módulo editorial estable; conservar referencias a medios, permisos y versiones. No equivale a una copia de seguridad completa del sitio.

## Orden recomendado

1. Integración de medios con el editor y recuperación del trabajo sin guardar.
2. Renderizado de imágenes adaptables y comprobación de uso antes de borrar.
3. Edición visual básica de imágenes y diagnóstico del sistema.
4. Revisiones y concurrencia de contenido cuando se defina el almacenamiento editorial.
5. Prototipo separado de editor de bloques/patrones, si hay una necesidad real de construir páginas desde administración.

No conviene incluir todo esto en la versión pendiente del cron. Son features nuevas que necesitan alcance y aceptación propios. Esta revisión no autoriza implementarlas.

## Fuentes verificables

Código local WordPress: wp-includes/media.php (wp_get_attachment_image, wp_calculate_image_srcset), wp-admin/includes/image-edit.php (wp_save_image), wp-admin/includes/image.php (wp_generate_attachment_metadata), wp-admin/includes/post.php (wp_check_post_lock, wp_create_post_autosave), wp-includes/revision.php, wp-includes/block-patterns.php y wp-admin/includes/class-wp-site-health.php.

- [Biblioteca multimedia](https://learn.wordpress.org/tutorial/using-the-media-library/).
- [Edición de medios](https://wordpress.org/documentation/article/edit-media/).
- [Imágenes adaptables](https://developer.wordpress.org/apis/responsive-images/).
- [Revisiones](https://wordpress.org/documentation/article/revisions/).
- [Patrones](https://wordpress.org/documentation/article/block-pattern/).
- [Editor de bloques independiente](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/).
- [Construir un editor de bloques](https://developer.wordpress.org/block-editor/how-to-guides/platform/custom-block-editor/).
- [Estado del sitio](https://wordpress.org/documentation/article/site-health-screen/).
