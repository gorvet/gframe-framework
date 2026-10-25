# Tareas — papelera

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-19
**Estado:** auditada

- [x] T1 — Trait ORM de lifecycle y consultas (CA-01–03). Verificar: nivel sistema; SQLite/MySQL, estado, byte equality, scope, revisión, rollback y conexión.
- [x] T2 — Pruebas adversarias y concurrencia (CA-01, CA-02, CA-04). Verificar: nivel sistema; procesos reales, un ganador por revisión; limpieza fallida no deja cambios.
- [x] T3 — Docs, skill, changelog y regresión (CA-03, CA-04). Verificar: nivel estructural/sistema; recetas y límites contra runtime; lint, skills e historial.

## Nota de trabajo

Skills canónicas orchestrator, framework-maintenance, backend y orm-models. Persistencia por ORM; sin SQL por motor en el trait. Auditoría posterior separada de implementación.
