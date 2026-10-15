# Cron runner

`cron-runner` registra y ejecuta tareas programadas mediante cron real del servidor o cron virtual activado por actividad HTTP. Ambos mecanismos utilizan el mismo scheduler y la misma protección contra concurrencia. Puede usarse para campañas, mantenimiento, limpieza y otros procesos generales.

## Instalación

El módulo publica `bin/gframe-cron.php` y crea las tablas `cron_tasks` y `cron_runtime`. En modo real, configure el cron del servidor para ejecutar periódicamente:

```bash
php bin/gframe-cron.php 50
```

El argumento opcional indica cuántas tareas puede reservar cada ejecución: el límite configurado (10 por defecto), entre 1 y 100. Es un límite de tareas, no de registros de negocio procesados por cada tarea.

El comando carga `core/Load.php`, adquiere el bloqueo compartido, incluye los registradores `config/cron/*.php`, reserva tareas vencidas y las ejecuta en ese mismo proceso CLI. La preparación común de `GFrame\Cron\CronExecutor` se utiliza también en el worker virtual. Instalar el módulo o registrar una tarea no pone en marcha un servicio permanente.

En Linux, puede programar una ejecución cada minuto con rutas absolutas del despliegue:

```cron
* * * * * /usr/bin/php /ruta/al/proyecto/bin/gframe-cron.php 50 >> /ruta/al/proyecto/storage/cron.log 2>&1
```

Adapte la ruta del ejecutable PHP y los permisos del usuario del cron. En Windows, configure el Programador de tareas para ejecutar el PHP CLI con la ruta absoluta de `bin/gframe-cron.php` y el argumento `50`. El proceso debe poder leer la configuración y conectarse a la base de datos; no depende de un usuario conectado en el navegador.

## Crear una tarea

Guarde un manejador autoloadable, por ejemplo en `app/services/maintenance/NotificationCleanupCron.php`. Debe extender `Cron` y poder construirse sin argumentos obligatorios:

```php
<?php
namespace App\Services\Maintenance;

final class NotificationCleanupCron extends \Cron
{
    protected string $name = 'notifications-cleanup';

    public function handle(array $task = []): array
    {
        $result = (new \GFrame\Notifications\NotificationService(
            new \GFrame\Notifications\NotificationModel()
        ))->cleanup();
        if (($result['status'] ?? '') !== 'success') {
            throw new \RuntimeException('No se pudo limpiar las notificaciones expiradas.');
        }
        return $result;
    }
}
```

Este ejemplo requiere el módulo `notifications` y marca lógicamente sus avisos expirados en todos los ámbitos. El payload recibido en `$task` contiene únicamente los datos que registró, no la fila completa de `cron_tasks`.

Registra la tarea desde un comando que haya cargado el arranque del proyecto o desde `config/cron/register-notification-cleanup.php`:

```php
use App\Services\Maintenance\NotificationCleanupCron;

$tasks = new \CronDataProvider();
if ($tasks->findByKey('maintenance.notifications.cleanup') === null) {
    $result = (new \CronTaskService($tasks))->schedule(
        'maintenance.notifications.cleanup',
        NotificationCleanupCron::class,
        gmdate('Y-m-d H:i:s'),
        [],
        86400
    );
    if (($result['status'] ?? '') !== 'success') {
        throw new \RuntimeException('No se pudo registrar la limpieza de notificaciones.');
    }
}
```

La clave identifica una tarea única en toda la tabla; use prefijos propios e incluya un ID de tenant si necesita tareas separadas por ámbito. Admite hasta 120 caracteres alfanuméricos, puntos, guiones, guiones bajos y dos puntos. Registrar de nuevo la misma clave devuelve `cron_task_exists` y no cambia su configuración. El guard del ejemplo evita registrar la tarea en cada ejecución.

Las fechas se guardan en UTC. Para una tarea única, omita el quinto argumento. Para una recurrente, ese argumento es el intervalo en segundos, con un mínimo de 60. El nombre de clase se almacena, pero `schedule()` no comprueba que exista o extienda `Cron`; esa comprobación ocurre al ejecutarla.

### Resultado del manejador

El scheduler utiliza `reschedule` para decidir si una tarea recurrente continúa. Devuelva `['reschedule' => false]` cuando haya terminado definitivamente. Si falta esa clave, una tarea con intervalo continúa por defecto; una tarea sin intervalo siempre termina.

El scheduler registra la tarea como fallida cuando `handle()` devuelve `status: error` o `status: failed`, utilizando `message`, luego `code`, como detalle. También acepta excepciones, como en el ejemplo. Un resultado sin `status` conserva el comportamiento anterior de éxito; otros estados no se reclasifican automáticamente. Un fallo no se reprograma aunque incluya `reschedule`. No persiste el array completo del resultado: guarde el historial de negocio en su propio modelo si lo necesita. Revise los handlers antiguos que devolvían errores esperando una reprogramación automática.

## Control de tareas

`CronTaskService` ofrece:

- `schedule()` para registrar una tarea única o recurrente.
- `reschedule()` para cambiar su próxima ejecución.
- `pause()` y `resume()` para controlar su actividad.
- `cancel()` para cancelarla definitivamente.

Todos los métodos devuelven contratos con `status`, `code` y, cuando corresponde, `data`.

```php
$service = new \CronTaskService();
$paused = $service->pause('maintenance.notifications.cleanup');
$resumed = $service->resume('maintenance.notifications.cleanup');
$changed = $service->reschedule('maintenance.notifications.cleanup', gmdate('Y-m-d H:i:s'));
```

`pause()` desactiva la reserva futura sin cambiar el estado de la fila. `resume()` la activa y la pone en `pending`, conservando la fecha. `reschedule()` cambia fecha, reactiva la tarea y limpia bloqueo/error. `cancel()` pone `cancelled` y desactiva la fila; no borra la tarea y no interrumpe un handler que ya esté ejecutándose. Una llamada posterior a `resume()` o `reschedule()` puede reactivarla.

Los métodos devuelven `cron_task_not_found` si no existe la clave. `schedule()` devuelve el ID en `data.task_id`; una clave/fecha inválida devuelve `invalid_cron_task` y un intervalo inferior al mínimo, `invalid_cron_interval`.

## Concurrencia y recuperación

El scheduler adquiere primero un bloqueo exclusivo y no espera si otro runner lo posee: devuelve `data.busy=true`. MySQL utiliza `GET_LOCK` por conexión, compartido por las instancias que utilizan la misma base en el mismo servidor primario. SQLite mantiene una transacción en una base auxiliar junto al archivo de datos, con sufijo `.gframe-cron-lock.sqlite`; requiere permisos de escritura en ese directorio y almacenamiento local con bloqueos SQLite fiables. No elimine ni sustituya ese archivo mientras haya procesos activos. Para instancias en hosts distintos, utilice MySQL compartido; las copias independientes de SQLite no se coordinan.

El bloqueo permanece durante registradores y handlers y no vence por antigüedad. La salida o caída del proceso libera la conexión/bloqueo. Solo después de adquirirlo se recuperan las filas `processing` que dejó un ejecutor interrumpido. No se usa un umbral fijo de 15 minutos para declarar muerto a un handler vivo. Las reservas individuales siguen siendo actualizaciones condicionales y se comprueba la propiedad antes de persistir el resultado.

Las tareas únicas terminan en `completed`. Las recurrentes vuelven a `pending` con una nueva fecha. Los errores quedan en `error`, con el detalle interno en `last_error`.

Las tareas se ejecutan secuencialmente, sin un límite de tiempo impuesto por el módulo. El próximo turno recurrente se calcula sumando el intervalo al mayor valor entre la hora actual y la fecha anterior; no recupera todos los períodos perdidos ni mantiene una hora fija de calendario. Una ejecución larga puede desplazar el horario.

La recuperación permite repetir tareas después de una interrupción. Si un proceso pierde su conexión a MySQL, el servidor puede liberar el bloqueo mientras el handler todavía realiza una operación externa; la comprobación de propiedad impide que finalice la reserva después de perderla, pero no revierte ese efecto externo. Diseñe handlers idempotentes y use claves de idempotencia del proveedor cuando estén disponibles. Los reintentos no garantizan efectos externos exactamente una vez.

Un fallo queda en `error`, sin reintento genérico automático. Tras corregirlo, reprograme la tarea; algunos módulos incorporan registradores con su propia política de recuperación. `attempts` cuenta reservas, no solo errores, y no se reinicia al completar una ejecución recurrente.

## Consultar y ejecutar desde PHP

Después de cargar el arranque del proyecto, puedes consultar una tarea y ejecutar un lote:

```php
$task = (new \CronDataProvider())->findByKey('maintenance.notifications.cleanup');
$result = (new \CronScheduler())->runDue(50);
```

La fila permite consultar `status`, `is_active`, `scheduled_at`, `locked_at`, `last_run_at`, `last_error` y `attempts`. `runDue()` devuelve `status`, nombre del cron, fechas UTC de inicio/fin y `data.processed`, `data.failed`, `data.recovered`. Los fallos individuales pueden coexistir con `status: success`; revise `data.failed`. El comando CLI termina con código 1 si falla la pasada o algún handler; código 0 también puede indicar que otro runner estaba ocupado y no se ejecutó trabajo.

`runDue()` no incluye los registradores automáticamente: eso lo hace `bin/gframe-cron.php`. Ejecútelo desde un comando o worker; llamarlo desde una petición web bloquearía la respuesta hasta terminar los handlers.

## Ámbito y ampliación

Las clases del proyecto pueden extender `Cron` y utilizar sus servicios o modelos. El scheduler instancia el handler sin inyección de argumentos; construya sus dependencias dentro del handler o en su constructor sin parámetros obligatorios. Para sustituir la persistencia, implemente `CronTaskRepository` e inyéctelo en `CronTaskService` y `CronScheduler`.

El scheduler no filtra por usuario o tenant ni ejecuta middleware de rutas. Pase el ámbito en el payload y aplique su autorización y filtros al consultar datos. Mantenga nombres de clase y payload bajo control del backend; no permita que un visitante registre clases arbitrarias. No guarde credenciales en el payload.

## Uso con notificaciones

El cron activa el procesador de campañas o de colas, pero no envía mensajes directamente:

```text
cron del servidor -> cron-runner -> procesador -> notification_queue -> transporte
```

Los addons pueden publicar registradores PHP en `config/cron/`; el punto de entrada CLI los carga antes de reservar tareas. `notifications-email` utiliza este mecanismo para mantener activa su tarea recurrente de consumo de la cola de correo.

## Seleccionar cron real o virtual

Las variables siguientes se leen directamente desde `.env`, sin necesidad de añadir claves al `config/app.php` existente:

```dotenv
GFRAME_CRON_MODE=virtual
GFRAME_CRON_MIN_INTERVAL=60
GFRAME_CRON_BATCH_LIMIT=10
# Si PHP CLI no se detecta automáticamente:
GFRAME_PHP_BINARY=/usr/bin/php
```

`GFRAME_CRON_MODE` admite `real` (valor predeterminado) o `virtual`. El intervalo admite enteros de 1 a 86400 segundos y el límite de 1 a 100 tareas; sus valores predeterminados son 60 y 10. Un valor inválido impide lanzar trabajo y queda en el diagnóstico; no se convierte en ejecución ilimitada. Las claves internas equivalentes son `cron.mode`, `cron.min_interval` y `cron.batch_limit`; `.env` tiene prioridad. El argumento del CLI puede sobrescribir el límite de esa pasada.

En modo virtual, Router registra un disparador al cierre de las peticiones web y AJAX, públicas o administrativas. Se cierra la escritura de sesión y, con PHP-FPM, se finaliza la respuesta mediante `fastcgi_finish_request` antes de comprobar/lanzar trabajo. En otros servidores, el lanzamiento tiene un coste breve, pero la petición no espera a los handlers. No se activan peticiones API, webhook, SSE ni procesos CLI. El worker usa `Async`, vuelve a cargar el proyecto y ejecuta los mismos registradores del CLI. No requiere polling JavaScript ni un endpoint público que permita elegir clases o tareas.

El intervalo se aplica globalmente por almacenamiento. MySQL utiliza el reloj del servidor de base de datos para esta coordinación. Antes de lanzar se reserva un ticket interno que caduca a los 120 segundos si el worker no llega a arrancar. Un worker retrasado no ejecuta con un ticket vencido o sustituido. Si falla el lanzamiento, se libera solo su ticket, se conserva el intervalo mínimo y se registra el error. Mientras otro runner mantiene el bloqueo, no se lanza otro. El cron real puede seguir ejecutándose en modo virtual y comparte la exclusión; normalmente conviene elegir una sola fuente de activación.

**Sin tráfico que ejecute PHP no hay ejecución puntual garantizada.** Una caché o CDN que sirva la página sin llegar a GFrame tampoco activa el cron. Una pestaña abierta sin peticiones no produce actividad. Use el modo real cuando el horario deba ser independiente de las visitas. Cambiar a `real` no instala automáticamente un cron del servidor ni cancela un worker ya iniciado.

## Diagnóstico y actualización

```bash
php bin/gframe-cron.php --status
```

El diagnóstico no ejecuta registradores ni handlers. Muestra configuración efectiva, si el bloqueo está ocupado, lanzamiento pendiente, último inicio/final, contadores y último error; las fechas de coordinación son timestamps Unix. El ticket secreto no se imprime. Un lanzamiento no prueba finalización: compruebe `finished_at`, los contadores y las filas de tareas. `last_error=cron_handlers_failed` remite al `last_error` de cada tarea. Los fallos que impiden acceder al esquema se registran con prefijo `[gframe.cron]`, como máximo una vez por minuto y host/instalación; el limitador de logs no participa en la coordinación de ejecución.

Para actualizar, detenga primero los runners de la versión antigua y espere a que terminen: no conocen el bloqueo nuevo. Después de instalar la versión validada, revise `composer gframe:update -- --dry-run --preserve-custom` y aplique `composer gframe:update -- --preserve-custom`. La migración `202610150001_cron_runtime.sql` conserva las tareas existentes. Resuelva los conflictos del ejecutor publicado y del header antes de activar el modo virtual. `.env` no se sobrescribe: añada sus variables manualmente. Compruebe el estado, ejecute una tarea de prueba sin efectos externos y reactive el cron elegido. Un sitio sin necesidad de tareas puede mantener `real` sin instalar ni programar cron.

Una implementación personalizada de `CronTaskRepository` conserva su interfaz, pero `CronScheduler` requiere ahora coordinación. Por defecto usa la base del framework; si el repositorio utiliza otro almacenamiento, inyecte un `GFrame\Cron\CronRuntime` construido con la conexión compartida adecuada y su esquema `cron_runtime`. No llame `recoverStale(0)` fuera de la ejecución coordinada.
