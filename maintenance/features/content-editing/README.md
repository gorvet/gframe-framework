# Protección editorial — etapas

**Spec:** [SPEC.md](SPEC.md).

## Ciclo activo

**Objetivo:** guardado atómico por revisión y aviso de otras sesiones.
**Por qué ahora:** siguiente prioridad autorizada después de cerrar cron y CSRF.
**Etapas comprometidas:** 01.
**Criterio de éxito:** una sola escritura gana por revisión, presencia caduca, permisos y datos ajenos se preservan.
**Fuera de este ciclo:** aplicaciones consumidoras y publicación de versiones.
**Siguiente decisión:** revisar los cambios para su entrega; las revisiones históricas de contenido son la siguiente prioridad y requieren su propio alcance.

| Etapa | Conciliación | Estado | Trabajo activo | Tareas | Auditoría |
|---|---|---|---|---|---|
| [01 Guardado y presencia](01-save-presence/CONCILIACION.md) | ver archivo | cerrada | ninguno | TAREAS.md auditada | pasada 1: coincide |

## Cierre

Guardado condicional y presencia optativa implementados, documentados y auditados. [Auditoría](01-save-presence/AUDIT.md) y [verificación](01-save-presence/VERIFICACION.md). Suite completa 522 pruebas/5226 aserciones; carreras SQLite/MySQL y navegador real aprobados. Cambios locales, sin release ni instalación en aplicaciones consumidoras.

R-01–R-06 pertenecen a 01. Riesgos dominantes: pérdida de datos y autorización; fronteras: procesos/DB y navegador/HTTP; evidencia máxima de este ciclo: sistema, con integración MySQL cuando esté disponible.
