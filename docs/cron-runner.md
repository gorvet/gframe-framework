# Cron runner

`cron-runner` registra y ejecuta tareas programadas desde la consola. Puede usarse para campañas, mantenimiento, limpieza y otros procesos que no deben depender de una petición web.

## Instalación

El módulo publica `bin/gframe-cron.php` y crea la tabla `cron_tasks`. Configure el cron del servidor para ejecutar periódicamente:

```bash
php bin/gframe-cron.php 50
```

El argumento opcional indica cuántas tareas puede reservar cada ejecución.

## Crear una tarea

El manejador debe extender `Cron`:

```php
final class CampaignDispatchCron extends Cron
{
    public function handle(array $task = []): array
    {
        return ['status' => 'success', 'campaign_id' => (int)($task['campaign_id'] ?? 0)];
    }
}
```

Después se registra mediante el servicio:

```php
$result = (new CronTaskService())->schedule(
    'campaigns.dispatch.42',
    CampaignDispatchCron::class,
    '2026-10-01 14:00:00',
    ['campaign_id' => 42]
);
```

Para una tarea recurrente, el quinto argumento es el intervalo en segundos. El intervalo mínimo es de 60 segundos.

## Control de tareas

`CronTaskService` ofrece:

- `schedule()` para registrar una tarea única o recurrente.
- `reschedule()` para cambiar su próxima ejecución.
- `pause()` y `resume()` para controlar su actividad.
- `cancel()` para cancelarla definitivamente.

Todos los métodos devuelven contratos con `status`, `code` y, cuando corresponde, `data`.

## Concurrencia y recuperación

El ejecutor cambia cada tarea de `pending` a `processing` mediante una actualización condicionada. Solo procesa las filas que pudo reservar. Antes de cada lote recupera tareas que hayan permanecido bloqueadas durante más de 15 minutos.

Las tareas únicas terminan en `completed`. Las recurrentes vuelven a `pending` con una nueva fecha. Los errores quedan en `error`, con el detalle interno en `last_error`.

## Uso con notificaciones

El cron activa el procesador de campañas o de colas, pero no envía mensajes directamente:

```text
cron del servidor -> cron-runner -> procesador -> notification_queue -> transporte
```

Los addons pueden publicar registradores PHP en `config/cron/`; el punto de entrada CLI los carga antes de reservar tareas. `notifications-email` utiliza este mecanismo para mantener activa su tarea recurrente de consumo de la cola de correo.
