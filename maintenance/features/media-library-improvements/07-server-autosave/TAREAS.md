# Tareas — Autoguardado privado

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

- [x] T1 — Persistencia privada, controlador auxiliar y esquemas del módulo (CA-01, CA-02).
  Verificar: sistema; scope, sanitización, base pública, expiración, tombstone y CAS; proceso separado concurrente y motores disponibles, con omisiones explícitas.
- [x] T2 — Integración TinyMCE y recuperación privada (CA-03).
  Verificar: sistema; navegador con backend PHP/SQLite real; pausa, intervalo, tokens, una petición, recuperar/deshacer/descartar, errores y retry, lectura y teardown.
- [x] T3 — Coordinar publicación y cambios en vuelo (CA-04).
  Verificar: sistema; nuevas ediciones y respuesta tardía, publicación con nueva revisión y limpieza condicional sin perder contenido posterior.
- [x] T4 — Guías, skills, migración y comprobaciones de entrega (CA-05).
  Verificar: estructural y sistema; installer/publisher, docs/enlaces, lint y suites PHP/JS completas de release; registrar límites.
## Nota de trabajo

Skills canónicas: orchestrator, backend, ORM, auth-access para scope normalizado, frontend-admin y framework-maintenance; integración de contenido existente. No modificar vendor ni sitios. El autoguardado remoto debe distinguirse de publicar y de sincronizar textarea. Nueva capacidad optativa, no convertir rich-text-editor básico en módulo con base de datos obligatoria.

Publicación posterior autorizada: commit, push y etiqueta Composer después de cerrar la auditoría; comprobar referencias remotas y no confundirlo con despliegue.
