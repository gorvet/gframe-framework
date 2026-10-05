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
