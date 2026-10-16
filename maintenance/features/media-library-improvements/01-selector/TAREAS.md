# Tareas — Selector multimedia

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

- [x] T1 — Listado con secuencia y subida con progreso/evento de lote (CA-01, CA-04).
  Verificar: `sistema`; navegador con respuestas desordenadas, fallo parcial y reapertura; solo resultados actuales y éxitos seleccionados.
- [x] T2 — Bandeja PHP, panel de atributos y composición (CA-02, CA-03).
  Verificar: `sistema`; navegador, guardado, errores y orden exacto al confirmar; datos y selección persistidos según resultado autorizado.
- [x] T3 — Compatibilidad, accesibilidad y documentación (CA-03, CA-04, CA-05).
  Verificar: `sistema`; pruebas PHP/JS, navegador escritorio/móvil, actualización y enlaces; sin regresiones de ámbitos ni modos existentes.

## Nota de trabajo

Skills canónicas: orchestrator, media-module, frontend-admin, backend y framework-maintenance. Pipeline UI: arquitectura sobre Bootstrap existente, panel lateral en escritorio y apilado en móvil; jerarquía biblioteca → archivo activo → selección → confirmar; sin segundo modal. Se aplican accesibilidad y revisión de calidad. No se generan tarjetas con HTML en JS: el backend devuelve fragmentos. No se altera la corrección ajena pendiente del toast.

Verificación de implementación: [VERIFICACION.md](VERIFICACION.md). No constituye auditoría de cierre.

Cierre posterior: [AUDIT.md](AUDIT.md), pase 4 coincide tras deltas 01–03; 35 pruebas PHP/1085 aserciones y cinco pruebas JS/navegador sin omisiones. La evidencia inicial se conserva como historial.
