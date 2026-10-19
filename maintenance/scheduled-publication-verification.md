# Publicación programada — verificación

2026-10-19. Prioridad 5 autorizada al continuar el itinerario. Framework independiente; sin cambios en consumidores ni nueva versión de Composer.

## Alcance y revisión

`ScheduledContent` añade fechas UTC, reprogramación y cancelación a modelos versionados. `ScheduledPublicationCronHandler` reutiliza cron recurrente y exige que el proyecto resuelva contexto y autorización vigente por registro. Se comparan revisión, estado y fecha antes de publicar. Papelera excluida cuando se usa `ContentTrash`; historial y publicación comparten transacción. Se conserva el contrato público de guardado; las nuevas precondiciones son métodos protegidos, no campos de petición.

Se revisaron conflictos por revisión antigua/mayúscula/espacio, tenant ajeno, fecha inválida/futura, cancelación, reprogramación, repetición, papelera, permisos retirados, OR pendiente y fallo de historial. Dos procesos compiten por una revisión y solo uno publica. El scheduler ejecuta el handler con repositorio y runtime reales, sin sesión del navegador.

Skills canónicas aplicadas: orchestrator, framework-maintenance, backend, orm-models y background-jobs. No se añaden hooks globales, tablas universales ni otro ejecutor.

## Evidencia

| Comando | Resultado |
|---|---|
| `php packages/phpunit/phpunit/phpunit --filter 'ScheduledContent\|ContentTrash\|ContentRevisions\|ContentEditingTest\|CronRunnerTest' --log-junit .phpunit.cache/publication-regression.xml` | 17 pruebas, 260 aserciones; sin omisiones; exit 0 |
| `GFRAME_PUBLICATION_TEST_ENGINE=mysql`, PHPUnit `--filter ScheduledContent --log-junit .phpunit.cache/publication-mysql.xml` | 2 pruebas, 41 aserciones; sin omisiones; exit 0 |
| `php -l` de los cinco archivos PHP afectados | Correctos |
| `php bin/validate-skills.php` | 14 skills, 79 enlaces; exit 0 |
| `git diff --check` | Sin errores |

Entorno Windows/PHP 8.3.35; SQLite y MySQL local con esquemas temporales eliminados al finalizar. La prueba entre procesos usa SQLite; lifecycle y scheduler se ejecutan en ambos motores. El primer fixture del scheduler pasó incorrectamente una conexión al constructor de ORM; se corrigió mediante una subclase de repositorio con conexión declarada. No cambia el runtime de cron.

## Límites de entrega

La aplicación integra tabla, rutas POST con auth/can/CSRF, lectores published, registrador y handler concretos. El cron del sistema y el despliegue no se configuran en esta tarea. No se garantiza puntualidad sin tráfico en cron virtual ni entrega externa exactamente una vez. Los registros sin permiso se omiten y conservan su programación; el proyecto decide corregirla/cancelarla para no ocupar permanentemente el principio del lote. Siguiente prioridad: salud del sitio.
