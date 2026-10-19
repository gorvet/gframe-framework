# Cron configurable — etapas de implementación

**Spec:** [SPEC.md](SPEC.md).
**Código:** `src/cron/`, `src/async/`, ciclo HTTP y módulo `cron-runner`; implementación realizada, ver [evidencia](VERIFICACION.md).

## Ciclo

1. Conciliar → CONCILIACION.md.
2. Tareas → TAREAS.md.
3. Implementar.
4. Auditar → AUDIT.md; ante brechas, TAREAS-delta-NN.md y nueva auditoría.

## Ciclo activo

**Objetivo:** seleccionar desde `.env` un cron real o virtual general y seguro.
**Etapas comprometidas:** 01–03; conciliaciones aprobadas por la solicitud de implementación.
**Por qué ahora:** la selección de modos está confirmada; resolver la concurrencia es requisito previo al disparador.
**Criterio de éxito:** ejecución en segundo plano con coordinación compartida y recuperación demostradas, configuración y actualización documentadas.
**Fuera de este ciclo:** aplicaciones consumidoras; CSRF se conserva en una feature independiente.
**Siguiente decisión:** ciclo cerrado; continuar las nuevas capacidades de contenido cuando se concilie su alcance.

## Estado del corte

| # | Carpeta | Resultado | Depende de | Conciliación | Estado | Trabajo activo | Tareas | Auditoría |
|---|---------|-----------|------------|--------------|--------|----------------|--------|-----------|
| 01 | [01-ejecucion-coordinada](01-ejecucion-coordinada/CONCILIACION.md) | Scheduler/CLI con coordinación y recuperación verificables | — | ver archivo | cerrada | ninguno | TAREAS.md auditada | pasada 1: coincide |
| 02 | [02-disparador-configurable](02-disparador-configurable/CONCILIACION.md) | Modo virtual por actividad HTTP y selección desde .env | 01 | ver archivo | cerrada | ninguno; delta 01 implementado | TAREAS.md auditada | pasada 2: coincide |
| 03 | [03-operacion-y-actualizacion](03-operacion-y-actualizacion/CONCILIACION.md) | Diagnóstico y actualización verificables en entornos declarados | 01, 02 | ver archivo | cerrada | ninguno; delta 01 implementado | TAREAS.md auditada | pasada 2: coincide |

## Cobertura

| Requisitos | Etapa responsable | Riesgo dominante | Frontera externa | Evidencia máxima prevista |
|------------|-------------------|------------------|------------------|--------------------------|
| R-05, R-06, R-07 | 01 | Concurrencia y recuperación | Procesos y base compartida | sistema |
| R-01, R-02, R-03, R-04, R-08 | 02 | Seguridad y rendimiento | HTTP → PHP CLI | sistema |
| R-09, R-10, R-12 | 03 | Operación y actualización | Host y ejecutor publicado | ambiental |
| R-11 | 01, 02; integración y regresión en 03 | Concurrencia y proceso externo | MySQL/SQLite, Windows/Linux | ambiental |

Las etapas 01–03 quedan cerradas el 2026-10-19. Se conservan las pasadas iniciales con huecos y los deltas de verificación. El bloqueo Linux está resuelto: repetición MySQL/procesos aprobada, job 118176309692. [Evidencia de cierre](CI-20261019.md): 83 pruebas/1676 aserciones locales y 15/126 Linux/MySQL. Las omisiones locales de migraciones de cron están cubiertas por Linux; la omisión adicional de autosave es ajena al cierre. No quedan tareas activas en este ciclo ni se actualizan aplicaciones consumidoras.

## Ampliación del ejecutor

[Etapa 04: CLI y HTTP](04-ejecutor-http/CONCILIACION.md). Estado: cerrada. [Tareas principales auditadas](04-ejecutor-http/TAREAS.md); [delta 01 implementado](04-ejecutor-http/TAREAS-delta-01.md). [Auditoría](04-ejecutor-http/AUDIT.md), pase 2: coincide, 33 pruebas y 1040 aserciones, dos pruebas de servidores reales omitidas. Sin trabajo activo de esta etapa. No se reabren ni se cierran auditorías anteriores; no implica publicación ni validación del servidor de producción.

Validación ambiental posterior finalizada: estado actual cerrada, sin trabajo activo; [delta 02 implementado](04-ejecutor-http/TAREAS-delta-02.md), pase 4 de auditoría coincide. Se corrigió la documentación de Nginx tras reproducir la cancelación del POST. [HTTPS/PHP-FPM](04-ejecutor-http/HTTPS-FPM.md): 1 prueba y 33 aserciones, sin omisiones; CI 38819363756 con nueve jobs aprobados. Se conservan los cierres y fallos anteriores como histórico.
