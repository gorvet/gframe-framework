# Tareas — etapa 03 Escritura

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

- [x] T1 — Fragmentos PHP de imagen/galería por IDs con comprobación estricta de ámbito/tipo y extensión acotada del sanitizador (CA-01, CA-03).
  Verificar: conductual; SQLite real para ámbitos, tipos, límites, orden y URL no confiable; HTML malicioso y round-trip.
- [x] T2 — Integración opcional TinyMCE/picker y ciclo de vida (CA-01, CA-02).
  Verificar: sistema; TinyMCE real con insertar/reeditar/ordenar/cancelar, cursor, undo/redo, dos instancias y respuesta tardía tras destruir.
- [x] T3 — Assets, documentación, skills y regresión UX (CA-04).
  Verificar: sistema y estructural; editor sin activación, saveAll y recarga saneada, móvil/teclado, pruebas anteriores, publicación de assets y enlaces/lint/diff.

## Nota de trabajo

Skills canónicas: orchestrator, media-module, frontend-admin, backend, framework-maintenance y core-architecture; receta content-editor-search. Conservar todos los cambios previos. Trabajar en originales del framework; no tocar TinyMCE externo ni instalaciones. Diseño sobre los controles TinyMCE y picker Bootstrap existentes. El contenido y copy de inserción proceden de PHP. Pruebas proporcionales y auditoría separada antes de cierre.

Evidencia de implementación y límites de transporte: [VERIFICACION.md](VERIFICACION.md). No equivale al veredicto de auditoría.

Cierre: [AUDIT.md](AUDIT.md), pasada 2 coincide tras delta 01; 56 pruebas PHP/1246 aserciones y seis JS/navegador, sin omisiones. La pasada 1 y la prueba roja de cambios pendientes se conservan.
