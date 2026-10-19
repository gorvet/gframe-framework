# Tareas — historial y restauración

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-19
**Estado:** auditada

## Listado

- [x] T1 — Modelo ORM de historial y guardado por herencia transaccional (CA-01, CA-03).
  Verificar: nivel sistema; guardado/restore, conflicto y fallo inyectado revierten conjuntamente; conexión nombrada conservada.
- [x] T2 — Lectura/comparación por scope y vaciado confirmado (CA-02, CA-04).
  Verificar: nivel sistema; 10 por defecto, límites 2/12, recurso y tenant ajenos, vaciado sin alterar contenido actual y comparación escapada.
- [x] T3 — Módulo, docs, skills y regresión (CA-05).
  Verificar: nivel sistema/estructural; migraciones repetibles MySQL/SQLite, ejemplos, lint, enlaces y suites de contenido.

## Trazabilidad

| CA | Tarea | Verificación |
|---|---|---|
| CA-01 | T1 | Atomicidad y rollback |
| CA-02 | T2 | Scope y comparación |
| CA-03 | T1 | Restore y conflicto |
| CA-04 | T2 | Retención y vaciado |
| CA-05 | T3 | Instalación y regresión |

## Verificación global

- [x] Tests y lints relevantes.
- [x] Docs y skills actualizadas.
- [x] Sin cambios a aplicaciones instaladas.

## Fuera de este listado

Otras prioridades, releases y purgas en sitios reales.

## Nota de trabajo

Skills canónicas orchestrator, framework-maintenance, backend y orm-models. Persistencia en modelos ORM; extensión por herencia sobre VersionedContentModel; transacción de la misma conexión. No se añade endpoint genérico ni selección de tablas/campos desde HTTP.
