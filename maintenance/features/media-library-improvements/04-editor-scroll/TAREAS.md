# Tareas — Editor con scroll propio

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

- [x] T1 — Altura inicial estable sin autoresize predeterminado y scroll contenido aislado (CA-01).
  Verificar: sistema; TinyMCE real con 180 párrafos, rueda, teclado y extremos; altura estable, iframe desplazable y aside visible sin mover el documento exterior.
- [x] T2 — Compatibilidad y documentación (CA-02).
  Verificar: sistema y estructural; navegador con documento corto, móvil y opciones por instancia; regresión de integración de biblioteca y guardado; lint, pruebas de editor, skills y enlaces.

## Nota de trabajo

Skills canónicas: orchestrator, frontend-admin, ui-design-clean, framework-maintenance y receta content-editor-search. Fuentes del módulo; no modificar TinyMCE externo ni layouts instalados. Conservar todos los cambios de etapas anteriores. Referencia: editor original y formulario Bootstrap con aside; no rediseñar la pantalla. Capturas y pruebas conductuales antes de cierre separado.

Evidencia de implementación: [VERIFICACION.md](VERIFICACION.md).

Cierre: [AUDIT.md](AUDIT.md), pasada 1 coincide; tres pruebas JS/navegador y 12 PHP/182 aserciones, sin omisiones.
