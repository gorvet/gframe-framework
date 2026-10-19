# Verificación de implementación — 2026-10-15

Evidencia de implementación, no auditoría ni cierre de etapas. Entorno: Windows, PHP 8.3.35, PHPUnit 10.5.65, SQLite y servidor MySQL/MariaDB local de pruebas. Las bases MySQL utilizadas tienen nombres aleatorios `gframe_cron_test_*` y `gframe_audit_*` y se eliminan al terminar. No se ejecutaron tareas de aplicaciones consumidoras ni se modificó producción.

## Procedimientos reproducibles

- `php packages/phpunit/phpunit/phpunit`: suite completa; resultado final registrado en las notas de tareas.
- `php packages/phpunit/phpunit/phpunit --filter 'Cron|Async|VisitorCsrf|AuthUi|MiddlewareDocumentation|SessionManager|RouteSessionPolicy|ProjectUpdateService'`: 77 pruebas y 1147 aserciones aprobadas antes de las ampliaciones finales de migración/CLI.
- Con `GFRAME_TEST_MYSQL_DSN` apuntando a un servidor de pruebas y `GFRAME_CRON_TEST_ENGINE=mysql`, ejecutar `php packages/phpunit/phpunit/phpunit --filter 'CronConcurrency|MySqlMigrationIntegration'`: 15 pruebas y 126 aserciones aprobadas, incluidas las ampliaciones finales de migración.
- `php packages/phpunit/phpunit/phpunit --filter VirtualCronHttpTest`: 1 prueba y 22 aserciones aprobadas, incluido el CLI publicado y su diagnóstico.
- `php bin/lint.php` y `php bin/validate-skills.php`: sintaxis y contratos de skills; resultado final en notas de tareas.

## Qué demuestra cada grupo

| Etapa y criterios | Pruebas / comprobación | Resultado observado |
|---|---|---|
| 01 CA-01, CA-03 | CronConcurrencyTest | Diez competencias de dos procesos, un ganador por turno; segundo runner ocupado durante handler largo con fecha de bloqueo antigua; bloqueo no reentrante. Ejecutado con SQLite y MySQL. |
| 01 CA-02 | CronConcurrencyTest y CronRunnerTest | Caída con exit(23), reserva huérfana recuperada, ticket obsoleto rechazado, pérdida de propiedad inyectada sin finalizar tareas ni escribir estado de un sucesor. |
| 01 CA-04 | VirtualCronHttpTest y CronRunnerTest | Async real carga bootstrap y registrador; CLI usa el mismo flujo; recurrencia y errores Exception/TypeError comprobados. |
| 01 CA-05; 03 CA-02 | CronConcurrencyTest, MySqlMigrationIntegrationTest y ProjectUpdateServiceTest | Instalación desde tablas ausentes, actualización con historial y tablas manuales compatibles sin historial; tareas conservadas, repetición de migración sin efectos y default incompatible rechazado. Dry-run no crea tabla; preserve-custom mantiene CLI/header y luego se comprueba publicación de los nuevos archivos. |
| 02 CA-01–CA-04 | CronConcurrencyTest y VirtualCronHttpTest | Modo real no invoca lanzador; virtual invoca Async, respuesta antes de terminar handler de 1,8 segundos, intervalo compartido, límite y competencia entre CLI y ticket virtual. |
| 02 CA-05 | Revisión Router/VirtualCron y pruebas de worker | Solo web/ajax registran el cierre; CLI no registra, ninguna ruta pública de ejecución añadida. El worker recibe únicamente ticket y límite elegidos por el servidor; registradores y clases proceden de almacenamiento/configuración internos. |
| 02 CA-06–CA-07; 03 CA-05 | CronConcurrencyTest y revisión de frontera de errores | Fallo de lanzamiento inyectado, liberación de su ticket, intervalo conservado, configuración inválida sin lanzamiento; falta de esquema se convierte en cron_unavailable y se registra sin cambiar la respuesta principal. |
| 03 CA-01, CA-03 | VirtualCronHttpTest, revisión de docs y skills | --status no ejecuta handlers ni expone el ticket; CLI termina en 1 con handler fallido. Docs explican caché, falta de tráfico y ausencia de exactamente una vez. |

La prueba de caída registra deliberadamente un efecto antes de terminar el proceso: tras recuperar la tarea se observan dos efectos. Esa prueba demuestra el límite de idempotencia externa, no un fallo de la exclusión durante una conexión viva.

## Límites pendientes

- Etapa 03 CA-04: Windows se ejercitó con procesos y HTTP reales. Linux no se ejecutó en esta sesión. Se añadieron jobs de CI para MySQL/Linux y Windows; su definición no prueba que hayan pasado.
- No se midió producción ni un despliegue distribuido en hosts distintos. Los procesos locales separados sobre MySQL prueban exclusión compartida; el contrato de operación exige un mismo primario y configuración coherente entre instancias. No se garantiza exclusión de efectos externos tras una partición de red.
- No hay AUDIT.md ni veredicto de auditoría. Las tareas sin evidencia ambiental completa permanecen pendientes.
- El validador externo de etapas emite advertencias por el formato del tipo de criterio aunque cada criterio declara explícitamente su tipo. La validación sin modo estricto comprueba el contrato estructural sin errores; no se modificó el validador global.

## Resultado final

Suite completa final: 491 pruebas, 4701 aserciones, 11 omitidas por requisitos de entorno, sin fallos. MySQL focalizado: 15 pruebas/126 aserciones, sin omisiones. Verificación focalizada de actualización/migraciones/HTTP/CSRF: 32 pruebas/877 aserciones, 6 omisiones MySQL cubiertas por la ejecución MySQL separada. Sintaxis: 431 archivos PHP válidos y lint posterior de MigrationRunner válido. Skills: 14 válidas y 79 enlaces comprobados. Markdown: 25 archivos con 0 enlaces locales ausentes. Revisión editorial: 23 textos y 0 errores críticos antes de las notas finales; revisión manual de estas notas.

La suite completa se ejecutó con `php packages/phpunit/phpunit/phpunit --log-junit .phpunit.cache/cron-csrf-final.xml` (salida 0). El XML es un artefacto local ignorado, no parte del commit.

## Cierre posterior — 2026-10-19

Los límites pendientes anteriores describen el corte histórico del 2026-10-15. Las etapas 01–03 ya tienen auditoría independiente con veredicto coincide. Se repitió Linux/MySQL (job 118176309692, 15 pruebas/126 aserciones, sin omisiones) y la suite local pertinente (83 pruebas/1676 aserciones; siete omisiones MySQL, seis cubiertas por Linux y una de autosave ajena al cierre). [Ledger reproducible](CI-20261019.md). El bloqueo Linux queda resuelto; sin trabajo activo ni cambios de runtime.
