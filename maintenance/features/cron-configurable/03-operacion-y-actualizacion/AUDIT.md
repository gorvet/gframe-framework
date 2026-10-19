# Auditoría — etapa 03

## Pasada 1 — 2026-10-19

**Contra:** TAREAS.md (principal).
**Baseline:** conciliación cerrada y tareas del 2026-10-15; commit `c8ccd983202621c5c2be29dde810870c8f8873a4`; Windows/PHP 8.3.35/SQLite. Auditoría intermedia solicitada con etapa previamente bloqueada.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Diagnóstico distingue inicio, fin, fallo y contadores sin token | T1 | cumple | E-01 | Ninguna material |
| CA-02 | Instalar/actualizar conserva tareas y CLI personalizado | T2 | cumple | E-01 | MySQL ambiental debe contrastarse aparte |
| CA-03 | Guía, variables y skills coherentes | T3 | cumple | E-02 | Ninguna material |
| CA-04 | Matriz Windows/Linux con recuperación y límites | T4 | parcial | E-01, E-03 | Linux anterior aprobado; reejecución independiente solicitada, todavía pendiente |
| CA-05 | Sin esquema o CLI, respuesta conservada y logs acotados | T5 | parcial | E-02 | Falta ejecutar ese fallo desde HTTP |

### Ledger de evidencia

| ID | CA | Procedimiento/comando | Esperado | Observado/exit | Entorno y timestamp | Artefacto/log | Limitación |
|---|---|---|---|---|---|---|---|
| E-01 | CA-01, CA-02, CA-04 | Mismos dos comandos y XML de la [auditoría 02](../02-disparador-configurable/AUDIT.md), E-01/E-02 | Diagnóstico y conservación correctos | 39/913 con 7 omisiones y 36/561 sin omisiones; exit 0 | Windows/PHP 8.3.35; 2026-10-19 | `.phpunit.cache/cron-csrf-*-20261019.xml` | Omisiones MySQL locales |
| E-02 | CA-03, CA-05 | Contrastar docs/cron-runner.md, .env.example, skill background-jobs y VirtualCron::logFailure | Contratos coherentes y fallo comprobado | Contrato estructural coherente; falta fallo HTTP integrado | Mismo snapshot; 2026-10-19 | Fuentes y tests | Inspección no acredita limitación de logs |
| E-03 | CA-04 | Consultar jobs/logs de Actions 38819363756 y solicitar repetición del job MySQL | Host Linux y competencia reales | CI previo Ubuntu 24.04.5/PHP 8.3.35/MySQL 8: 15 pruebas/126 aserciones; repetición solicitada | Consulta 2026-10-19; ejecución previa 2026-10-15 | https://github.com/gorvet/gframe-framework/actions/runs/38819363756 | No se hereda el verde histórico como cierre |

**Veredicto:** no coincide

### Verificación global

Validador estructural sin errores; advertencias históricas de formato de tipos CA. Baseline intacto. No se reparó runtime durante la pasada.

### Desviaciones de alcance

Ninguna.

### Huecos

[Delta 01](TAREAS-delta-01.md). La ausencia de evidencia Linux ya no se trata como ausencia de implementación.

## Pasada 2 — 2026-10-19

**Contra:** TAREAS.md (principal), mismo baseline; delta 01 implementado y bloqueo B-01 resuelto por ejecución Linux repetida. Etapa reanudada en auditando antes de este cierre; las dependencias 01 y 02 ya tienen veredicto coincide.

| Criterio | Afirmaciones auditadas | Tareas | Resultado | Evidencia reproducible | Limitaciones |
|---|---|---|---|---|---|
| CA-01 | Estado de inicio/fin, intentos, contadores, ocupado y error; status no ejecuta ni expone ticket; CLI falla con handler fallido | T1 | cumple | E-10 | Diagnóstico del último lote; un lote posterior vacío puede sustituir contadores |
| CA-02 | Instalar/migrar ambos motores conserva tareas; preview sin escrituras, preserve-custom conserva CLI y publicación actualiza ejecutor | T2 | cumple | E-10, E-11 | Fixtures aislados, sin actualizar aplicaciones reales |
| CA-03 | Guía/env/skills reflejan rangos, modos, caché, tráfico e idempotencia | T3 | cumple | E-10, E-12 | Contraste estructural y ejemplo documental ejecutado |
| CA-04 | Matriz Windows/SQLite y Linux/MySQL: diez competencias sin duplicado, handler vivo, recuperación, lotes y real/virtual | T4 | cumple | E-10, E-11 | Perfiles, ventana, umbrales y SHA en ledger; sin certificación de producción |
| CA-05 | Sin esquema o CLI utilizable, respuesta intacta, ticket liberado y un solo log en intervalo | T5 | cumple | E-10 | Fallos reales sobre infraestructura temporal |

### Ledger de evidencia

[CI-20261019.md](../CI-20261019.md), E-10/E-11/E-12. Linux repetido el 2026-10-19 a 12:38 America/Havana, job 118176309692 completed/success, 15 pruebas/126 aserciones, sin omisiones. Equivalencia del snapshot de cron comprobada; no se hereda el resultado previo de implementación. Windows reaudita las mismas competencias y añade fronteras HTTP/CSRF con 83 pruebas/1676 aserciones. Ninguna omisión material queda pendiente.

**Veredicto:** coincide

### Verificación global

T1–T5 y métodos de verificación cubiertos; baseline intacto. Revisión inversa de código, instalación, migraciones, updater, guías y skills. Validador sin errores y advertencias de formato histórico registradas. No se modificó runtime durante esta pasada.

### Desviaciones de alcance

Informativa: pruebas compartidas con etapas 02 y CSRF; no añaden producto. Sin desviaciones bloqueantes. Etapa cerrada, sin trabajo activo. B-01 queda como antecedente histórico resuelto, sin reescribir CONCILIACION.md.
