# Tareas — guardado por revisión y presencia

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-19
**Spec:** [SPEC.md](../SPEC.md)
**Estado:** auditada

## Listado

- [x] T1 — Modelo de guardado condicional por revisión (CA-01, CA-02; R-01/R-02).
  Impacto: persistencia y contrato optativo por herencia.
  Verificar: nivel sistema; competencia de procesos SQLite, revisión antigua, aislamiento, conexión nombrada y rollback; solo affected decide ganador. Añadir integración MySQL si disponible.
- [x] T2 — Presencia por scope y controlador autorizado (CA-03, CA-04; R-03/R-04).
  Impacto: esquema optativo, sesiones y caducidad.
  Verificar: nivel sistema/conductual; pings, dos sesiones, tenant/recurso ajenos, expiración, identidad manipulada y petición inválida; ningún nombre/token ajeno en respuestas.
- [x] T3 — Componente nativo de aviso (CA-05; R-05).
  Impacto: lifecycle, tokens y estado del formulario.
  Verificar: nivel conductual/sistema; una petición activa, pausa/retorno de visibilidad, destrucción y respuesta antigua; contenido no reemplazado y revisión solo tras confirmación explícita.
- [x] T4 — Instalación, documentación, skills y regresión (CA-06; R-06).
  Impacto: manifest, migración, docs y recetas canónicas.
  Verificar: nivel sistema/estructural; catálogo, migraciones repetibles, publicación de assets, ejemplos de guardado ejecutables, enlaces, lint y pruebas de módulos/ORM/autosave.

## Trazabilidad

| Criterio | Tareas | Verificación |
|---|---|---|
| CA-01 | T1 | Procesos concurrentes y revisión antigua |
| CA-02 | T1 | Scope, conexión y rollback |
| CA-03 | T2 | Caducidad y aislamiento |
| CA-04 | T2 | Controlador y rutas documentadas |
| CA-05 | T3 | Lifecycle del cliente |
| CA-06 | T4 | Instalación, docs, skills y regresión |

## Verificación global

- [x] Tests y lints relevantes.
- [x] Documentación/config/migraciones coherentes.
- [x] Sin cambios fuera del alcance aprobado.

## Fuera de este listado

No modificar aplicaciones instaladas, publicar versiones, introducir colaboración ni otras prioridades.

## Nota de trabajo

Skills canónicas: orchestrator, framework-maintenance, backend, orm-models, auth-access y frontend-admin. Preservar roadmap previo. Persistencia en modelos; scopes explícitos de servidor; componentes y assets desde manifest/meta. No duplicar ORM, permisos ni sistema de rutas.
