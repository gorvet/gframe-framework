# Contadores temporales en base de datos

Disponible desde GFrame 5.0.0. El cambio del almacenamiento predeterminado requiere aplicar la migración antes de atender solicitudes protegidas.

`AuthRateLimiter` y `MailRateLimiter` usan `driver => 'database'` por defecto. Los contadores se guardan en tablas SQL; no se crean archivos JSON, TXT ni otros archivos locales para persistirlos. Una conexión caída o una migración pendiente devuelve el código de almacenamiento no disponible, sin cambiar a archivos ni permitir la operación protegida.

## Configuración

Incorpora estas claves a los bloques existentes de `config/app.php`:

```php
'auth' => [
    'rate_limit' => [
        'driver' => 'database',
        'connection' => 'main',
        'enabled' => true,
        'window_seconds' => 900,
        'ip_attempts' => 100,
        'login_attempts' => 10,
        'mail_attempts' => 5,
    ],
],
'mail' => [
    'rate_limit' => [
        'driver' => 'database',
        'connection' => 'main',
        'enabled' => true,
        'max_attempts' => 5,
        'window_seconds' => 3600,
    ],
],
```

`main` debe ser una conexión MySQL/MariaDB configurada por la aplicación. Las credenciales permanecen en `.env`. `connection => null` selecciona la conexión predeterminada de `DatabaseManager`. Todos los servidores y workers que compartan cupos deben apuntar a la misma base de datos y usar el mismo `APP_KEY` para correo. Una base de datos compartida entre aplicaciones también comparte sus contadores; usa bases separadas cuando necesites aislarlos.

La implementación también admite SQLite para entornos que lo requieran; para producción sin almacenamiento local, utiliza MySQL/MariaDB. No se crea una conexión SQLite implícitamente.

Las variables `MAIL_RATE_LIMIT_ENABLED`, `MAIL_RATE_LIMIT_MAX_ATTEMPTS` y `MAIL_RATE_LIMIT_WINDOW_SECONDS` conservan su prioridad. El correo solo consume cupo si la llamada incluye la opción `rate_limit`; los envíos internos que la omiten siguen sin este límite.

## Instalación y actualización

El módulo técnico `rate-limits` proporciona la migración `202611030001_rate_limits.sql` para MySQL y SQLite. Crea tres tablas: `gframe_rate_locks`, `gframe_rate_buckets` y `gframe_rate_reservations`, con índices de caducidad. En MySQL usa InnoDB. La instalación nueva crea el esquema y registra su baseline; el actualizador ejecuta la migración en proyectos existentes.

`auth-ui` incorpora `rate-limits` como dependencia. Si solo utilizas el limitador de correo, añade `rate-limits` a la **lista completa** de módulos registrados mediante `composer gframe:update -- --modules=LISTA_COMPLETA_CON_RATE_LIMITS`, primero con `--dry-run --preserve-custom`. No reemplaces la lista por el único módulo nuevo.

Si configuras una conexión diferente de la utilizada por el actualizador, aplica la migración también sobre esa conexión, desde una tarea CLI de despliegue con el proyecto ya inicializado:

```php
$pdo = \DatabaseManager::connection('main');
if (!$pdo instanceof \PDO) {
    throw new \RuntimeException('Conexión de contadores no disponible.');
}
\GFrame\Install\MigrationRunner::frameworkDefault()->migrate(
    $pdo,
    $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME),
    ['rate-limits']
);
```

No es necesario parchear el paquete instalado. Actualiza `gorvet/gframe` mediante Composer y ejecuta el actualizador del proyecto. Detén temporalmente las solicitudes protegidas durante el cambio para que todos los nodos adopten el mismo almacenamiento. Los contadores del almacenamiento anterior no se importan: empieza una ventana nueva. Los archivos anteriores dejan de consultarse y pueden retirarse al completar el despliegue.

El driver `file` se ha eliminado. Una configuración antigua que lo solicite se rechaza; no se crea ningún archivo. Los constructores aceptan una conexión PDO opcional para integración o pruebas, en lugar de un directorio.

## Concurrencia, caducidad y limpieza

Cada reserva abre una transacción breve y bloquea una fila permanente del limitador. La autenticación comprueba y consume conjuntamente el cupo de IP y el de identidad. Una petición rechazada no consume ninguno. Mantiene la ventana fija y los grupos de operaciones existentes.

Correo mantiene la ventana móvil. Guarda una reserva individual antes del envío y libera exclusivamente esa reserva si el envío síncrono o el lanzamiento asíncrono falla. SMTP se ejecuta fuera de la transacción; no mantiene bloqueos SQL durante la conexión de correo. Una excepción del transporte también intenta liberar la reserva y se propaga. Si la base de datos falla al devolver el cupo, este puede permanecer ocupado hasta caducar. Un proceso interrumpido también deja caducar su reserva.

Un fallo SMTP posterior a una aceptación asíncrona conserva el comportamiento anterior: no devuelve el cupo; el worker tampoco consume otro. Los códigos `auth_rate_limited`, `auth_rate_limit_unavailable`, `mail_rate_limited`, `mail_rate_limit_invalid` y `mail_rate_limit_unavailable`, sus mensajes y `data.retry_after` en segundos se mantienen.

Cada reserva elimina registros caducados de su limitador. La caducidad no depende de cron. Para eliminar registros durante períodos sin solicitudes, la aplicación puede registrar esta operación en su mantenimiento programado:

```php
$store = \GFrame\Security\DatabaseRateLimitStore::configured('auth.rate_limit');
$deleted = $store->prune(time());
```

`prune()` limpia ambos limitadores de esa conexión y devuelve la cantidad de filas eliminadas. Si correo utiliza otra conexión, ejecútalo también para `mail.rate_limit`. Las dos filas de bloqueo se conservan siempre. Las identidades de autenticación se guardan como hashes y las de correo como HMAC; no se almacena el correo o la IP en texto claro.

El limitador es dueño de sus transacciones. Si se invoca sobre una conexión que ya tiene una transacción activa, rechaza la operación sin confirmar ni revertir la transacción del llamador. Configura una conexión independiente si necesitas usarlo dentro de una operación de negocio transaccional.

`lock_timeout_seconds` en cada bloque vale 2 por defecto. Un bloqueo SQL que supera esa espera devuelve el código de almacenamiento no disponible. La opción limita la espera de bloqueo, no la conexión inicial ni la duración total de SQL o SMTP. La configuración de espera de la conexión se restaura después de cada transacción.

La limpieza impide recuperar intentos ya eliminados si se amplía posteriormente la ventana. Define las ventanas antes del despliegue y conserva los relojes de los servidores sincronizados.
