# Colas de notificaciones

GFrame proporciona una cola persistente opcional mediante `NotificationQueueModel`, la coordinación mediante `NotificationQueueService` y un transporte de correo listo para utilizar.

El flujo estándar utiliza el modelo y el transporte de correo:

```php
use GFrame\Notifications\EmailNotificationTransport;
use GFrame\Notifications\NotificationQueueModel;
use GFrame\Notifications\NotificationQueueService;
use GFrame\Notifications\NotificationQueueWorker;

$queue = new NotificationQueueService(
    new NotificationQueueModel(),
    new EmailNotificationTransport()
);

$queue->enqueue('email', 'persona@example.com', [
    'subject' => 'Aviso',
    'body' => '<p>Contenido del mensaje</p>',
]);

$worker = new NotificationQueueWorker();
$result = $worker->run($queue, 120);
```

Los proyectos que necesiten otro canal implementan `NotificationTransport`. Para procesamiento específico también pueden implementar `NotificationBatchProcessor`.

Para lanzar un procesador construible sin argumentos en segundo plano:

```php
$worker->dispatchAsync(ProjectNotificationProcessor::class, 120);
```

El framework normaliza el tamaño del lote, captura errores y devuelve una respuesta estable. Las plantillas, destinatarios y reglas de negocio permanecen en la aplicación.
