# CSRF para visitantes — etapas de implementación

**Spec:** [SPEC.md](SPEC.md).
**Código:** sesiones, middleware, templates y formularios públicos del framework; implementación realizada, ver [evidencia](VERIFICACION.md).

## Ciclo

1. Conciliar → CONCILIACION.md.
2. Tareas → TAREAS.md.
3. Implementar.
4. Auditar → AUDIT.md; ante brechas, delta y nueva auditoría.

## Ciclo activo

**Objetivo:** conservar y concretar la protección CSRF pública solicitada.
**Etapas comprometidas:** 01; conciliación aprobada por la solicitud de implementación.
**Por qué ahora:** necesidad detectada durante el análisis; se conserva independiente mientras se prioriza cron configurable.
**Criterio de éxito:** formularios públicos protegidos con sesiones anónimas, sin romper login, caché ni rutas sin sesión.
**Fuera de este ciclo:** cron y aplicaciones consumidoras.
**Siguiente decisión:** auditoría separada de la implementación.

## Estado del corte

| # | Carpeta | Resultado | Depende de | Conciliación | Estado | Trabajo activo | Tareas | Auditoría |
|---|---------|-----------|------------|--------------|--------|----------------|--------|-----------|
| 01 | [01-formularios-publicos](01-formularios-publicos/CONCILIACION.md) | Formularios de visitantes con CSRF verificable | — | ver archivo | auditando | principal implementado | TAREAS.md | pendiente |

## Cobertura

R-01–R-06 pertenecen a 01; riesgo dominante: seguridad y regresiones de sesión; frontera: navegador/HTTP/sesión; evidencia máxima propuesta: sistema.
