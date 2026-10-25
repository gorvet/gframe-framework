# Revisiones de contenido — etapas

**Spec:** [SPEC.md](SPEC.md).

## Ciclo activo

**Objetivo:** historial con comparación y restauración autorizada.
**Por qué ahora:** siguiente prioridad autorizada después del guardado por revisión ya auditado y subido en `ee23253`.
**Trabajo actual:** ninguno; retención configurable y vaciado autorizado implementados y auditados.
**Fuera del ciclo:** aplicaciones consumidoras y publicación de versiones.

| Etapa | Conciliación | Estado | Trabajo activo | Tareas | Auditoría |
|---|---|---|---|---|---|
| [01 Historial y restauración](01-history-restore/CONCILIACION.md) | ver archivo | cerrada | ninguno | TAREAS.md auditada | pasada 1: coincide |

## Cierre

Historial optativo con 10 revisiones configurables y vaciado confirmado implementado y auditado. Ver [AUDIT.md](01-history-restore/AUDIT.md) y [VERIFICACION.md](01-history-restore/VERIFICACION.md). Incluido en 4.3.0; la corrección posterior de restauración/publicación se entregó en 4.3.1. No modifica aplicaciones consumidoras.

La propuesta mantiene los modelos ORM y permisos de cada aplicación. No sustituye ni reabre la etapa cerrada de protección del guardado.
