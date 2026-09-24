# Colas de notificaciones

GFrame proporciona la infraestructura para ejecutar colas de notificaciones sin imponer tablas, usuarios, plantillas ni canales a las aplicaciones.

Cada proyecto implementa `GFrame\Notifications\NotificationBatchProcessor` y entrega su adaptador a `NotificationQueueWorker`:

```php
use GFrame\Notifications\NotificationBatchProcessor;
use GFrame\Notifications\NotificationQueueWorker;

final class ProjectNotificationProcessor implements NotificationBatchProcessor
{
    public function processNotificationBatch(int $batch): array
    {
        return ['status' => 'success', 'processed' => $batch];
    }
}

$worker = new NotificationQueueWorker();
$result = $worker->run(new ProjectNotificationProcessor(), 120);
```

Para lanzarlo en segundo plano, el adaptador debe poder construirse sin argumentos:

```php
$worker->dispatchAsync(ProjectNotificationProcessor::class, 120);
```

El framework normaliza el tamaño del lote, captura errores y devuelve una respuesta estable. El proyecto conserva la selección de destinatarios, persistencia, reglas, contenido y entrega por canales.
