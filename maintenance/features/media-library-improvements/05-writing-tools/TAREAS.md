# Tareas — Herramientas de escritura

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

- [x] T1 — Agrupar herramientas secundarias con controles nativos y respetar overrides (CA-01).
  Verificar: sistema; grupo abre por teclado, herramientas presentes; modo sliding personalizado no pierde el grupo; escritorio/móvil sin desbordamiento.
- [x] T2 — Acciones contextuales de imagen/galería sobre el flujo existente (CA-02).
  Verificar: sistema; TinyMCE real cambia/edita desde el contexto, quita imagen/grupo y deshace, conserva IDs de biblioteca y dirty.
- [x] T3 — Puente opcional al botón del formulario y atajo (CA-03).
  Verificar: sistema; botón/Control+S, validación, contenido actual, disabled, lectura, target ajeno, petición pendiente e instancias; sin envío directo ni éxito inventado.
- [x] T4 — Documentación, skills y regresión (CA-04).
  Verificar: sistema y estructural; escritura/scroll y personalización anterior, publicación/overrides, PHP/JS y enlaces; revisión de capturas.

## Nota de trabajo

- [x] T5 — Ampliar escritura de texto con formatos contextuales, menú «/» y contador nativo (CA-05).
  Verificar: sistema; teclado, filtrado, cancelación, inicio de párrafo y deshacer en TinyMCE real.

Skills canónicas: orchestrator, frontend-admin, ui-design-clean, media-module y framework-maintenance; receta content-editor-search. Fuentes propias del módulo; no tocar vendor TinyMCE, instalaciones ni cambios previos. Referencias oficiales de toolbar_groups y contexto contrastadas; comprobar contra TinyMCE 8.6.0 incluido. HTML/copy desde PHP; controles nativos del editor. Guardado reutiliza el botón existente del mismo formulario, sin servicios de artículos nuevos.
