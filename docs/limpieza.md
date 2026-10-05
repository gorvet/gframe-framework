# Limpieza y mantenimiento de datos

GFrame dispone de mecanismos de caducidad en sesiones y notificaciones. El mantenimiento se realiza desde el servidor o tareas programadas; Heartbeat no debe borrar archivos ni datos mientras el usuario navega.

## Mecanismos disponibles

| Área | Comportamiento actual |
| --- | --- |
| Sesiones en base de datos | `DatabaseSessionHandler::gc()` elimina filas vencidas |
| Sesiones Redis | Las claves de sesión caducan mediante TTL |
| Sesiones nativas | La retención depende del manejador PHP configurado |
| Notificaciones | `NotificationService::cleanup()` marca como eliminados avisos vencidos |
| Tareas Async | El worker elimina el archivo temporal del payload cuando lo lee |
| Logs | `LogHelper` añade entradas; no aplica rotación ni retención |

La recolección de sesiones PHP depende de la configuración y la frecuencia del servidor; un registro vencido no permite acceso aunque todavía no se haya eliminado físicamente. La limpieza de notificaciones es una operación explícita, no un borrado automático de todos los avisos leídos.

## Sesiones vencidas

Para una tarea CLI del proyecto ya iniciado, puedes usar el manejador con la conexión que almacena las sesiones:

```php
<?php
use GFrame\Session\DatabaseSessionHandler;

$database = DatabaseManager::connection('main');
if (!$database instanceof PDO) {
    throw new RuntimeException('No se pudo abrir la conexión de sesiones.');
}
$removed = (new DatabaseSessionHandler($database))->gc(1800);
```

`gc()` utiliza el campo `expires_at` guardado en las filas, no recalcula su vencimiento a partir del argumento. Adapta la conexión si `session.connection` no es `main`. No elimines sesiones activas como una operación de limpieza; para revocar accesos usa el contrato de [Sesiones](sesiones.md).

## Notificaciones vencidas

Cuando el módulo de notificaciones está instalado:

```php
<?php
use GFrame\Notifications\NotificationModel;
use GFrame\Notifications\NotificationService;

$result = (new NotificationService(new NotificationModel()))->cleanup();
```

La operación establece `is_deleted` y `deleted_at` en avisos cuya fecha de expiración ya pasó. No borra físicamente las filas ni elimina avisos sin vencimiento. Es una operación global de mantenimiento, sin filtro de usuario o tenant: ejecútala únicamente desde un proceso administrativo autorizado. Devuelve `notifications_cleaned` con `data.deleted`, o `notifications_cleanup_failed`.

## Programación y logs

Si instalas [Cron runner](cron-runner.md), registra un handler del proyecto que invoque las operaciones de mantenimiento necesarias. El servidor debe ejecutar su entrada CLI con la frecuencia elegida; declarar una tarea no configura el cron del alojamiento. Para un sitio sin ese módulo, utiliza una tarea CLI propia programada por el servidor.

`LogHelper::write($data, 'nombre')` escribe en `logs/nombre.txt`. El framework no limita su tamaño ni elimina entradas antiguas. Configura rotación y retención en el servidor, protege el directorio frente al acceso web y evita guardar tokens, contraseñas o datos personales innecesarios.

## Archivos y límites de la herramienta

No hay un comando general del core para purgar logs, cachés, temporales huérfanos o archivos multimedia sin referencias. Las funciones de caducidad anteriores no sustituyen esa herramienta.

Para una limpieza específica del proyecto, establece directorios permitidos, antigüedad mínima, exclusiones de procesos activos y una ejecución de comprobación sin borrado. Resuelve rutas absolutas antes de eliminar y no borres un directorio completo por su nombre. Las relaciones de archivos multimedia y la retención de datos de negocio deben comprobarse mediante sus servicios, no mediante un recorrido genérico de carpetas.


## Diseñar una tarea de mantenimiento

Una tarea de limpieza debe ser idempotente: ejecutarla dos veces no debe borrar datos adicionales por accidente ni corromper el estado. Separe selección, validación y borrado; no elimine mientras todavía está descubriendo qué elementos pertenecen al conjunto.

Para datos propios del proyecto, defina antes de automatizar:

- qué estado convierte un registro en candidato;
- cuánto tiempo debe conservarse;
- qué relaciones bloquean su eliminación;
- si se necesita borrado lógico o físico;
- qué auditoría debe conservarse;
- qué tamaño máximo procesa cada ejecución.

## Lotes y tiempo de ejecución

No presuponga que una limpieza completa cabe en una sola ejecución de cron. Para tablas o directorios grandes, procese lotes acotados y permita que la próxima ejecución continúe. Así se reducen bloqueos, memoria y riesgo de superar el tiempo permitido por el entorno.

Mantenga una clave o criterio estable para continuar; no dependa únicamente del offset de una consulta si el propio proceso elimina filas.

## Dry-run y observabilidad

Para herramientas destructivas propias, implemente una modalidad de inspección que informe qué se eliminaría sin modificar datos. Registre cantidad procesada, omitida y fallida, pero no incluya secretos ni contenido personal innecesario.

Una tarea programada debe permitir distinguir «no había nada que limpiar» de «la tarea falló antes de consultar». Use códigos o métricas propias en lugar de interpretar texto de logs.

## Archivos

Antes de borrar un archivo, resuelva su ruta absoluta y confirme que permanece dentro de un directorio permitido. No concatene directamente una ruta recibida del usuario ni siga enlaces simbólicos fuera del ámbito esperado sin una política explícita.

Para Media Library, utilice sus relaciones y servicios; una búsqueda por nombre de archivo no demuestra que un recurso esté huérfano.

## Recuperación y seguridad

Para limpiezas irreversibles, mantenga backup y pruebe primero sobre una copia de datos. Evite ejecutar un comando destructivo nuevo directamente en producción con un conjunto grande.

Si una tarea falla a mitad, la siguiente ejecución debe poder continuar desde el estado persistido. Las transacciones ayudan en cambios relacionados, pero no pueden revertir efectos externos como archivos ya eliminados o llamadas a terceros.

## Lista de comprobación

Antes de programar una limpieza, confirme ámbito, retención, idempotencia, lote, dry-run, logs, backup, relaciones, permisos del proceso y comportamiento ante ejecución simultánea. Para concurrencia periódica utilice las garantías de [Cron runner](cron-runner.md) y añada protección de negocio cuando una operación pueda durar más que el bloqueo del scheduler.
