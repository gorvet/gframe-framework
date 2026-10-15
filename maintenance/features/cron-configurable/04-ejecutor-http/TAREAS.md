# Tareas — etapa 04

**Principal:** sí
**Origen:** CONCILIACION.md cerrada 2026-10-15
**Estado:** auditada

## Listado

- [x] T1 — Configurar modo/ejecutor y evitar registro virtual en real (CA-01).
  Verificar: `sistema`; prueba HTTP/estado sin callback ni acceso a base.
- [x] T2 — Añadir transporte HTTP acotado y endpoint autenticado con tickets compartidos (CA-02, CA-03, CA-04).
  Verificar: `sistema`; servidor HTTP temporal, llamadas reales y virtuales, rechazo de entradas incorrectas y prueba negativa de efectos.
- [x] T3 — Publicar endpoint, documentar combinaciones y verificar actualización (CA-05).
  Verificar: `sistema`; pruebas de publicación/actualización y documentación, lint y regresiones de cron.

## Fuera de este listado

Publicación, despliegue, supervisor, historial y optimización de cola vacía.

## Notas de trabajo

Skills canónicas: orchestrator, background-jobs, integrations y framework-maintenance. Se preservan cambios ajenos de medios. Sin modificación del contrato CronTaskRepository ni migraciones nuevas.

## Evidencia final

Suite completa: 496 pruebas, 4728 aserciones, 11 omitidas, sin fallos. Prueba HTTP final adicional: 5 pruebas y 29 aserciones. MySQL: 15 pruebas y 126 aserciones. Lint: 435 archivos. Skills: 14 y 79 enlaces. Ver [VERIFICACION.md](VERIFICACION.md). Auditoría separada pendiente; TLS/proxy/servidores reales no verificados.

Auditoría posterior: [AUDIT.md](AUDIT.md), pase 2 coincide tras delta 01; 33 pruebas, 1040 aserciones, dos omitidas de servidores reales. La evidencia anterior se conserva como historial; TLS/FPM/proxy de producción siguen sin verificarse.
