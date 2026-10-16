# Mejoras de biblioteca multimedia

**Spec:** [SPEC.md](SPEC.md).

## Ciclo activo

Objetivo: mejorar selección y gestión sin sustituir permisos, ámbitos, originales ni componentes de GFrame. Por qué ahora: el usuario autorizó implementar después del inventario WordPress/GFrame y continuar con la gestión. Las capacidades posteriores permanecen pendientes y no se confunden con funcionalidades terminadas.

| Etapa | Estado | Trabajo activo |
|---|---|---|
| [01 Selector](01-selector/CONCILIACION.md) | cerrada | Sin trabajo activo; AUDIT.md pase 4 coincide |
| [02 Gestión](02-management/CONCILIACION.md) | cerrada | Sin trabajo activo; AUDIT.md pasada 1 coincide |
| [03 Escritura](03-writing/CONCILIACION.md) | cerrada | Sin trabajo activo; AUDIT.md pasada 2 coincide tras delta 01 |
| [04 Scroll propio](04-editor-scroll/CONCILIACION.md) | cerrada | Sin trabajo activo; AUDIT.md pasada 1 coincide |
| [05 Herramientas](05-writing-tools/CONCILIACION.md) | cerrada | AUDIT.md pasada 1 coincide |
| [06 Protección](06-writing-protection/CONCILIACION.md) | cerrada | AUDIT.md pasada 1 coincide; protección, recuperación, concentración y esquema |
| [07 Autoguardado servidor](07-server-autosave/CONCILIACION.md) | cerrada | AUDIT.md pasada 1 coincide; entrega 4.1.0 preparada y publicación autorizada |

Prioridad actual: escritura integrada con la biblioteca antes de procesamiento visual y metadatos adicionales. Sin cambios a sitios instalados ni publicación de versión en estas etapas.

Ajuste UX posterior: scroll propio de TinyMCE verificado con 180 párrafos, rueda/teclado, aside Bootstrap, redimensionado y opciones por instancia. [Etapa 04](04-editor-scroll/AUDIT.md), herramientas de texto de etapa 05 y protección de etapa 06 cerradas. Cambios locales sin publicar. Recuperación optativa mediante clave de ámbito y confirmación del guardado por cada aplicación; cinco pruebas JS/navegador y 13 PHP/198 aserciones pasan en la auditoría de etapa 06.

Escritura integrada y verificada: [auditoría de etapa 03](03-writing/AUDIT.md), pasada 2. Inserción/sustitución de imágenes y reedición de galerías, conservando cursor, cambios pendientes y ciclos del editor. 56 pruebas PHP/1246 aserciones y seis JS/navegador pasan. La prueba nueva usa TinyMCE y PHP reales con transporte interceptado; HTTP vivo no se verificó por bloqueo de sockets local. Cambios locales sin commit ni etiqueta. Edición de imágenes y metadatos permanecen aplazados.

Primera entrega: selección de subidas, progreso, atributos existentes, composición ordenada y controles de teclado. Ver [verificación](01-selector/VERIFICACION.md) y [auditoría](01-selector/AUDIT.md). Los deltas conservan hallazgos y comprobaciones. Cambios locales, sin commit ni publicación de esta entrega.

Segunda entrega: lista/orden, URL y filtros, navegación de detalles entre páginas y audio/vídeo. Ver [verificación](02-management/VERIFICACION.md) y [auditoría](02-management/AUDIT.md). 36 pruebas PHP/1136 aserciones y seis JS/navegador pasan sin omisiones. Cambios locales, sin commit ni etiqueta. La continuación priorizada por el usuario es escritura, etapa 03; edición visual y metadatos quedan después.
