# Cron runner

`cron-runner` registra y ejecuta tareas programadas desde la consola. Puede usarse para campañas, mantenimiento, limpieza y otros procesos que no deben depender de una petición web.

## Instalación

El módulo publica `bin/gframe-cron.php` y crea la tabla `cron_tasks`. Configure el cron del servidor para ejecutar periódicamente:

```bash
php bin/gframe-cron.php 50
```

El argumento opcional indica cuántas tareas puede reservar cada ejecución: 10 por defecto, entre 1 y 100. Es un límite de tareas, no de registros de negocio procesados por cada tarea.

El comando carga `core/Load.php`, incluye los registradores `config/cron/*.php`, reserva tareas vencidas y las ejecuta en ese mismo proceso CLI. Instalar el módulo o registrar una tarea no pone en marcha un servicio permanente.

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

El scheduler no interpreta `status: error` devuelto por `handle()` como un fallo. Para que registre `error` y `last_error`, lance una excepción, como en el ejemplo. Tampoco persiste el array de resultado del handler: guarde el historial de negocio en su propio modelo si lo necesita.

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

El ejecutor cambia cada tarea de `pending` a `processing` mediante una actualización condicionada. Solo procesa las filas que pudo reservar. Antes de cada lote recupera tareas que hayan permanecido bloqueadas durante más de 15 minutos.

Las tareas únicas terminan en `completed`. Las recurrentes vuelven a `pending` con una nueva fecha. Los errores quedan en `error`, con el detalle interno en `last_error`.

Las tareas se ejecutan secuencialmente, sin un límite de tiempo impuesto por el módulo. El próximo turno recurrente se calcula sumando el intervalo al mayor valor entre la hora actual y la fecha anterior; no recupera todos los períodos perdidos ni mantiene una hora fija de calendario. Una ejecución larga puede desplazar el horario.

La recuperación de bloqueos permite reintentos después de una interrupción. Un handler que tarde más de 15 minutos puede solaparse con otra ejecución del runner; diseñe operaciones idempotentes o añada su propia protección de negocio. La reserva no garantiza que un efecto externo ocurra exactamente una vez.

Un fallo queda en `error`, sin reintento genérico automático. Tras corregirlo, reprograme la tarea; algunos módulos incorporan registradores con su propia política de recuperación. `attempts` cuenta reservas, no solo errores, y no se reinicia al completar una ejecución recurrente.

## Consultar y ejecutar desde PHP

Después de cargar el arranque del proyecto, puedes consultar una tarea y ejecutar un lote:

```php
$task = (new \CronDataProvider())->findByKey('maintenance.notifications.cleanup');
$result = (new \CronScheduler())->runDue(50);
```

La fila permite consultar `status`, `is_active`, `scheduled_at`, `locked_at`, `last_run_at`, `last_error` y `attempts`. `runDue()` devuelve `status`, nombre del cron, fechas UTC de inicio/fin y `data.processed`, `data.failed`, `data.recovered`. Los fallos individuales pueden coexistir con `status: success`; revise `data.failed`. Por la misma razón, el comando CLI puede terminar con código 0 aunque una tarea haya fallado.

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
