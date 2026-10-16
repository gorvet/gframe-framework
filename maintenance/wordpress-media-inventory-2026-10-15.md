# Biblioteca multimedia: inventario ampliado WordPress/GFrame

Fecha: 2026-10-15. Investigación autorizada; no implementación. Amplía [los flujos iniciales](wordpress-media-flows-2026-10-15.md) sin limitarse a los ejemplos del usuario. La comparación se basa en código local (WordPress declara 7.1), documentación oficial y fuentes de GFrame. No se inició WordPress ni una aplicación instalada, ni se realizó una prueba visual de navegador. No se certifica accesibilidad completa, rendimiento o comportamiento móvil solo por inspección.

Skills canónicas: gframe-orchestrator, gframe-media-module; gframe-frontend-admin para las convenciones de interacción. Antes de implementar se aplicarán los especialistas de backend/seguridad y el flujo de diseño que corresponda. Se conservan cambios ajenos y la etiqueta del cron continúa pendiente.

## Lectura de estados y evidencia

Existente: hay implementación en el módulo revisado; no significa certificación visual. Parcial: hay parte del flujo o un servicio sin interfaz equivalente. Pendiente: no se encontró esa capacidad en el módulo revisado. Condicional: depende de configuración, editor o servidor. Propuesta: mejora nuestra, sin atribuirla a WordPress.

Fuentes de evidencia:

- **W1:** wordpress/wp-includes/js/media-views.js: selección, estados Library/GalleryEdit, detalles, navegación y teclado.
- **W2:** wordpress/wp-includes/js/plupload/wp-plupload.js: cola, porcentajes, errores y recuperación específica de tamaños.
- **W3:** wordpress/wp-includes/media-template.php: detalles, campos de adjunto, galerías y presentación.
- **W4:** wordpress/wp-admin/includes/class-wp-media-list-table.php y wp-includes/default-constants.php: vistas, orden, autor, acciones y MEDIA_TRASH=false predeterminado.
- **W5:** wordpress/wp-admin/includes/{image,image-edit}.php y wp-includes/media.php: procesamiento, EXIF, PDF, tamaños, edición y renderizado.
- **G1:** resources/modules/media-library/javascript/media-library.js: listado, filtros, AJAX y subida.
- **G2:** media-picker.js y media-field.js del mismo directorio: selección y resultado del campo.
- **G3:** media-admin.js y application/app/views/media-library/{mediaIndex,mediaEditModal,mediaPickerModal,_mlist}.php: interfaz publicada.
- **G4:** src/GFrame/Media/{MediaLibraryService,MediaModel,MediaProcessor,MediaScopeResolver,RemoteMediaInspector}.php, configuración y rutas del módulo.
- **G5:** docs/media-library.md y tests/{MediaLibrary,MediaRuntime,MediaScopeResolver,MediaMountIdentifiers}Test.php: contrato y cobertura existente; no se ejecutaron como parte de esta investigación documental.

## Inventario por recorrido completo

| # | Capacidad | WordPress nativo o condición | GFrame y evidencia | Decisión propuesta |
|---|---|---|---|---|
| 01 | Subida individual y múltiple | Sí; cola de archivos, W2 | Existente: multipart por archivo, G1 | Conservar |
| 02 | Arrastrar archivos | Sí, W2 | Existente: drop zone, G1/G3 | Conservar y verificar teclado/móvil |
| 03 | Progreso por archivo | Sí: loaded/percent, W2 | Parcial: spinner y mensajes, sin porcentaje en uploader revisado, G1 | Alta prioridad: cola visible con estado por archivo |
| 04 | Resultado de lote | Cola y errores por archivo, W2 | Parcial: avisos separados y recarga al terminar, G1 | Resumen de correctos/fallidos y reintento explícito |
| 05 | Reintento de procesamiento | WordPress tiene recuperación específica de subtamaños tras ciertos errores; no equivale a subida resumible universal, W2 | Sin flujo equivalente identificado, G1/G4 | Diseñar solo cuando se separe subir de procesar |
| 06 | Selección tras subir | autoSelect en Library, W1 | Pendiente: recarga sin seleccionar nuevo ID, G1/G2 | Alta prioridad |
| 07 | Límite efectivo visible | Depende de hosting y WordPress | Parcial: backend comunica límite del procesador; PHP/proxy pueden imponer menos, G1/G4/G5 | Mostrar límite efectivo calculable y errores claros |
| 08 | Búsqueda, tipo y fecha | Sí, W1/W4 | Existente: nombre/original_name/alt, tipo, mes y origen, G1/G4 | Conservar y mejorar vacíos/estados |
| 09 | Vista cuadrícula/lista | Sí, W4 | Solo cuadrícula nativa revisada, G3 | Lista opcional útil para documentos y gestión |
| 10 | Orden del listado | Columnas ordenables en tabla, W4 | Orden fijo media_id descendente, G4 | Nombre, fecha y tamaño, con claves permitidas en servidor |
| 11 | Filtrar por autor o mis archivos | Autor/mine en tabla, W4 | Propiedad por scope no equivale a filtrar uploader, G4 | Evaluar filtro por autor dentro del ámbito permitido |
| 12 | Preferencias de visualización | Vistas y opciones de tabla, W4 | No identificadas equivalentes, G1/G3 | Baja prioridad; nunca convertir preferencias en autorización |
| 13 | Detalles desde biblioteca | Sí, W1/W3 | Existente, G3 | Unificar con selector |
| 14 | Detalles editables desde selector | Sí, W1/W3 | Pendiente, G2/G3 | Alta prioridad: panel dentro del mismo modal |
| 15 | Alt, título, leyenda y descripción | Campos distintos según tipo/soporte, W3 | Parcial: backend nombre visible/alt; UI solo alt, G3/G4 | Separar etiquetas; definir esquema de leyenda/descripción |
| 16 | Guardado de metadatos y feedback | Guardado de detalles desde diálogo, W1 | Botón Guardar en pantalla principal, G3 | Guardado explícito inicial, estado y errores; evitar perder cambios |
| 17 | Siguiente/anterior en detalles | Botones y flechas, W1 | Botones existentes ocultados por media-admin.js, G3 | Completar navegación en conjunto filtrado |
| 18 | Copiar URL y descargar | Ambas acciones, W3/W4 | Copiar URL existente; sin acción dedicada de descarga en detalle revisado, G3 | Añadir descarga donde corresponda; URL externa no es descarga local |
| 19 | Reproducir audio/vídeo | Detalles con reproductor, W3 | Icono/extensión para no imágenes, G3 | Reproductor de formatos compatibles; fallback y carga bajo demanda |
| 20 | Datos de audio/vídeo | Duración y otros datos según formato/servidor, W3/W5 | Metadatos básicos; sin experiencia equivalente, G3/G4 | Prioridad posterior a imágenes/selector |
| 21 | Miniaturas de PDF | Intento condicionado por editor/capacidad del servidor, W5 | Icono para documentos, G3/G4 | Opcional, con fallback; no añadir dependencia obligatoria sin motivo |
| 22 | Selección simple/múltiple | Sí, W1 | Existente con máximo y persistencia entre páginas, G2 | Conservar y completar flujo de subida |
| 23 | Selección visible completa | Vista de selección, W1 | Conteo y marcado de tarjetas visibles; sin bandeja completa equivalente, G2 | Bandeja con todas las elegidas, quitar/limpiar y foco activo |
| 24 | Galería y orden | GalleryEdit y Gallery block, W1/W3 | Lista ordenada de IDs, pero sin editor de composición/reordenación, G2 | Alta prioridad tras panel de detalles |
| 25 | Opciones de galería | Columnas, tamaños y otras según editor, W3 | No incluidas en campo de IDs, G2 | Separar composición de presentación; definir contrato antes de añadir |
| 26 | Editar imagen sin salir al escritorio | Recortar, girar, voltear, escalar/restaurar, W5 | Métodos de procesamiento sin editor visual equivalente, G4 | Feature posterior con original conservado |
| 27 | Orientación de fotos | Procesamiento de orientación EXIF, W5 | No encontrado flujo EXIF equivalente en procesador revisado, G4 | Investigar fotos de móvil con fixtures antes de diseñar corrección |
| 28 | Imágenes grandes y tamaños | WordPress tiene tratamiento de imagen grande y subtamaños, W5 | Variantes configurables; originales conservados, G4 | Límites de píxeles/memoria y derivados adecuados; no copiar umbral ciegamente |
| 29 | Imagen adaptable al dispositivo | srcset/sizes generados por API, W5 | Variantes sin helper equivalente de renderizado identificado, G4 | Feature de renderizado, coordinada con editor |
| 30 | Acciones de gestión masivas | Borrado y otras según contexto/configuración, W4 | Selección múltiple del picker no es borrado masivo; delete por ID, G2/G4 | Diseñar después del selector, con errores parciales y permisos |
| 31 | Papelera/restauración | Condicional: MEDIA_TRASH es false por defecto, W4 | Borrado permanente, G3/G4 | No afirmar que WordPress siempre ofrece papelera; evaluar retención por separado |
| 32 | Adjuntar/desadjuntar a contenido | Función ligada al modelo de posts, W4 | Cada consumidor guarda sus IDs, G4/G5 | No copiar post_parent ni reintroducir tabla genérica de relaciones |
| 33 | Inventario fiable de uso | Adjuntar no enumera todas las referencias posibles | No hay consulta de consumidores antes de borrar, G4 | Propuesta GFrame: proveedores de usos con cobertura declarada |
| 34 | Teclado y estado accesible | Selección con teclado, navegación y estados, W1 | Parcial: enlaces clicables; marcado is-selected sin estado accesible equivalente identificado, G2/G3 | Alta prioridad junto con cualquier cambio al selector |
| 35 | Nombre accesible de miniatura | WordPress maneja etiquetas del adjunto, W1 | Enlace de tarjeta de imagen contiene img alt="" sin nombre explícito, G3 | Corregir semántica de la tarjeta, sin usar alt editorial como etiqueta universal |
| 36 | Modal y móvil | Diálogo con controles responsivos; la calidad real requiere navegador | Bootstrap/modal-xl, cuadrícula de varias columnas, G3 | No declarar equivalencia móvil; probar foco, lector, zoom y tamaños estrechos |
| 37 | Cancelar y efectos persistentes | Subida y metadatos no son lo mismo que insertar | Cancelar selección no borra subidas, G1/G2 | Conservar y comunicar; proteger respuestas tardías al reabrir |
| 38 | Biblioteca por contexto | Capacidades de adjuntos y modelo propio de WordPress | Global/user/tenant y capacidades separadas, G4 | Preservar nuestra arquitectura; no debilitarla al copiar UI |
| 39 | Privacidad del archivo | Un adjunto no implica almacenamiento privado | Nuestro ámbito de listado tampoco protege URL pública, G4/G5 | Mantener aviso; almacenamiento privado es feature distinta |
| 40 | Desde URL | Insertar/enlazar no equivale siempre a importar archivo | Hotlink existente con inspección remota, G1/G4 | Conservar distinción y probar selección del resultado |
| 41 | Cuota y sincronización | Dependencias/configuración propias de WordPress | Servicios/rutas existentes, sin controles nativos completos, G1/G3/G4 | Valorar exponer consumo; no llamar ausente a una API que sí existe |

## Hallazgos transversales propios de GFrame

Estos puntos no se atribuyen a ventajas de WordPress ni se presentan como bugs reproducidos:

- **Respuestas fuera de orden en búsqueda:** G1 aplica cada respuesta AJAX al contenedor; no se encontró cancelación o secuencia que descarte una búsqueda anterior. Una respuesta lenta puede sustituir una más reciente. Verificar con respuestas retrasadas antes de corregir.
- **Accesibilidad de imágenes:** G3 deja el alt vacío y no incluye nombre explícito en el enlace de tarjeta para imágenes; los data-* no proporcionan nombre accesible. Definir una etiqueta de selección basada en nombre visible y estado. El alt editorial vacío puede ser correcto en contenido y no debe rellenarse artificialmente para arreglar la interfaz.
- **Borrado y archivos:** G4 elimina la fila antes de borrar originales/variantes. Un fallo de disco exige definir resultado y recuperación; no afirmar atomicidad entre DB y filesystem. Revisar con inyección de fallo en la futura feature de borrado.
- **Versiones de metadatos:** dos editores pueden sobrescribir atributos; no se observó control de versión del medio. Definir concurrencia si se introduce guardado automático.
- **Permisos visibles:** rutas exigen capacidades distintas; eso no prueba que cada botón se adapte a ellas. Probar cuentas de solo lectura, subida sin edición y sin borrado; el servidor seguirá siendo autoridad.

## Funciones que no deben confundirse con el WordPress básico

No basar el plan en asumir carpetas virtuales, categorías de medios preconfiguradas, sustitución física conservando todos los enlaces, renombrado físico seguro, limpieza completa de no usados, ZIP masivo, edición masiva de todos los metadatos o compresión/conversión avanzada como funciones universales del core. Pueden requerir plugins o integraciones. WordPress sí ofrece APIs/extensiones y capacidades condicionales; eso no equivale a tener toda su interfaz instalada.

Tampoco es necesario copiar páginas públicas de adjuntos, comentarios de archivos, asociación a posts o listas de reproducción solo por existir. La reutilización en un framework debe responder a los proyectos que lo consumen.

## Prioridades propuestas tras revisar el conjunto

**Primer conjunto: completar el uso cotidiano.** Cola con progreso/errores por archivo; selección automática de subidas válidas; panel reutilizable de atributos; todas las seleccionadas visibles; semántica/teclado correctos; carga sin respuestas obsoletas. Servirá tanto a formularios como a escritura.

**Segundo conjunto: gestión de biblioteca.** Lista ordenable, filtros útiles, navegación de detalles, descarga y previsualización de formatos compatibles. Borrado masivo solo junto con un contrato de fallos y confirmación adecuados. Mantener separados seleccionar, quitar de contenido y borrar archivo.

**Tercer conjunto: galería.** Composición/reordenación con reapertura y persistencia exacta; después, inserción/reedición en el editor de escritura. La presentación y las leyendas por uso necesitan contrato propio. No convertir galerías en un nuevo ámbito de almacenamiento.

**Cuarto conjunto: procesamiento avanzado.** EXIF, edición visual no destructiva, derivados adaptables y posibles miniaturas PDF. Añadir capacidades del servidor, límites y recuperación antes de prometer formatos o procesamiento universal.

Este orden es una recomendación; no está conciliado ni autoriza implementación. Gutenberg continúa limitado a escritura y medios dentro del texto; no se propone construir páginas completas.

## Verificación pendiente antes de cerrar alcance

Comparación visual reproducible en instalaciones temporales de ambos sistemas: escritorio/móvil, teclado y foco, cola con éxito/error parcial, filtros, selección al subir, permisos, datos no guardados, galería y eliminación. La copia de código no demuestra por sí sola que el flujo se vea o funcione igual. Pruebas específicas para EXIF/PDF/memoria y fallos de almacenamiento requieren fixtures y capacidades reales.

## Documentación oficial contrastada

- [Biblioteca: cuadrícula/lista, detalles, filtros y acciones](https://wordpress.org/documentation/article/media-library-screen/).
- [Carga de archivos](https://wordpress.org/documentation/article/media-add-new-screen/).
- [Edición de medios e imágenes](https://wordpress.org/documentation/article/edit-media/).
- [Galerías dentro del contenido](https://wordpress.org/documentation/article/gallery-block/).
- [Imágenes adaptables](https://developer.wordpress.org/apis/responsive-images/).
- [Reproductor de vídeo](https://wordpress.org/documentation/article/video-block/).

Fuentes de código primarias W1–W5 acreditan los detalles técnicos y condiciones, en especial papelera, progreso y procesamiento de imágenes. No se usaron afirmaciones de plugins o foros como prueba de capacidades del core.
