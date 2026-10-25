# Tareas — preview privada

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-19
**Estado:** auditada

- [x] T1 — Lectura privada y revisión de estado (CA-01, CA-02).
  Verificar: nivel sistema; SQLite, sesión real, scopes y copia antigua; headers y contrato de autorización explícito.
- [x] T2 — Polling y botón propio del editor (CA-03, CA-04).
  Verificar: nivel conductual; estado repetido/nuevo, fallo, visibilidad/destrucción y URLs externas rechazadas; sin publicación.
- [x] T3 — Módulo, docs, skills y regresión (CA-04).
  Verificar: nivel estructural/sistema; manifest/meta, lint y pruebas de contenido/editor.

## Trazabilidad

| Criterio | Tareas |
|---|---|
| CA-01 | T1 |
| CA-02 | T1 |
| CA-03 | T2 |
| CA-04 | T2, T3 |

## Fuera de este listado

Barra administrativa, sitios consumidores, releases y publicación de contenidos.

## Nota de trabajo

Skills canónicas orchestrator, maintenance, backend, frontend-admin y frontend-public. Reutilizar ContentDraftService; rutas de negocio conservan auth/can/CSRF; assets por manifest/meta, sin HTML nuevo desde JS.
