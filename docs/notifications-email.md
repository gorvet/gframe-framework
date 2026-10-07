# Transporte de correo para notificaciones

`notifications-email` envía notificaciones por correo mediante `GFrame\Mail\MailService`. Incluye el transporte `email`, la plantilla general y un procesador de cola con reintentos.

## Instalación

El addon depende de `notifications` y `cron-runner`. Publica la plantilla general en `app/views/templates/mail/` y un registro de tarea en `config/cron/register-email-queue.php`.

## Responsabilidad

El transporte convierte cada notificación en destinatario, asunto, identificador de plantilla y variables. El soporte Mail del núcleo resuelve `.env`, renderiza la plantilla y ejecuta PHPMailer.

## Configuración

SMTP se configura exclusivamente en `.env`:

```dotenv
MAIL_HOST=smtp.example.com
MAIL_PORT=465
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Mi aplicación"
```

El addon no instala tablas, rutas ni pantallas administrativas. Utiliza la tabla `notification_queue` y el servicio Mail existentes.

## Plantillas

Las plantillas se comparten con el soporte Mail y viven en `app/views/templates/mail/`. Cada plantilla puede tener un archivo HTML y un JSON opcional con su nombre y variables.

La plantilla incluida se llama `notification`. Recibe `title`, `message` y `action_url`; el transporte calcula `action_display` para mostrar u ocultar el botón «Ver detalles». El núcleo añade el tema, logo, saludo y datos del sitio según los contratos de [Mail](mail.md).

## Encolar desde un servicio

En una acción web, guarde el trabajo y deje que el cron realice el envío:

```php
use GFrame\Notifications\NotificationQueueModel;
use GFrame\Notifications\NotificationQueueService;
use GFrame\Notifications\Email\EmailNotificationTransport;

$queue = new NotificationQueueService(
    new NotificationQueueModel(),
    new EmailNotificationTransport()
);
$result = $queue->enqueue('email', 'ana@example.com', [
    'subject' => 'Ana, tu informe está disponible',
    'template' => 'notification',
    'recipient_name' => 'Ana',
    'variables' => [
        'title' => 'Tu informe está disponible',
        'message' => 'Puedes consultar el informe desde tu cuenta.',
    ],
    'action_url' => 'https://example.com/account/reports',
], $tenantID);
```

El resultado correcto es `status: success`, `code: notification_queued` y el ID del trabajo en `data.notification_id`. Confirma que quedó guardado, no que el destinatario lo recibió. El feedback web debe indicar que la solicitud fue aceptada mediante los [contratos del frontend](frontend-core.md).

`$tenantID` es el ámbito del trabajo; use `null` en un proyecto sin tenants. La dirección del destinatario se proporciona expresamente: el transporte no busca el correo por ID de usuario ni comprueba su membresía. Valide destinatario y autorización en su servicio antes de encolar.

| Campo del payload | Uso |
| --- | --- |
| `subject` | Asunto del correo; si falta, se usa `title` del payload o «Notificación» |
| `template` | ID de plantilla, sin extensión; por defecto `notification` |
| `recipient_name` | Nombre del destinatario que Mail utiliza para la comunicación personalizada |
| `variables` | Valores para los marcadores de la plantilla; si falta, se utiliza el payload completo |
| `action_url` | URL absoluta HTTP/HTTPS; una ruta relativa se descarta |

Cuando incluye `variables`, coloque dentro los textos de la plantilla. El asunto y el título son independientes: un `title` dentro de `variables` no sustituye el asunto. Los valores se escapan durante el renderizado; no utilice `message` como contenedor de HTML arbitrario.

## Ejecutar el transporte

El registro permite integrar correo con otros canales:

```php
use GFrame\Notifications\NotificationTransportRegistry;
use GFrame\Notifications\Email\EmailNotificationTransport;

$registry = new NotificationTransportRegistry();
$registry->register('email', new EmailNotificationTransport());
$result = $registry->dispatch('email', [
    'recipient' => 'ana@example.com',
    'payload' => [
        'subject' => 'Tu informe está disponible',
        'recipient_name' => 'Ana',
        'variables' => ['title' => 'Informe disponible', 'message' => 'Consulta tu cuenta.'],
    ],
]);
```

Esta llamada espera al SMTP en el proceso actual. Utilícela dentro de un worker o comando, no como alternativa al envío en segundo plano de un formulario. El registro devuelve `notification_dispatched` al terminar el transporte; ante un fallo, `notification_transport_failed`. Ninguno de esos resultados confirma la entrega en la bandeja del destinatario.

## Cola y reintentos

Configure el cron del servidor para ejecutar `php bin/gframe-cron.php` al menos una vez por minuto. En cada ejecución, el registrador del addon asegura la tarea recurrente `notifications.email.queue`; `CronScheduler` la ejecuta cada 60 segundos. El procesador reserva exclusivamente filas del canal `email`. Los intentos fallidos vuelven a `pending` con una nueva fecha `available_at` hasta alcanzar `notifications.email.max_attempts` (5 por defecto). Después quedan en estado `failed`. El número de intentos se incrementa al reservar la fila, una sola vez por envío.

También puede invocarse `EmailQueueProcessor` con un repositorio y un transporte inyectados para pruebas o para un trabajador propio. `NotificationQueueWorker::runProcessorClass(EmailQueueProcessor::class)` funciona con las dependencias predeterminadas. No llame a `sendTemplateAsync()` desde este procesador: la cola ya proporciona la ejecución diferida y debe conocer si el envío SMTP tuvo éxito para decidir el reintento.

Desde un comando que ya haya cargado el bootstrap del proyecto:

```php
use GFrame\Notifications\Email\EmailQueueProcessor;

$result = (new EmailQueueProcessor())->processNotificationBatch(120);
```

`data` contiene `processed`, `sent`, `retried` y `failed`. El resultado global puede ser `success` aunque haya correos fallidos: revise esos contadores. El lote se limita a entre 20 y 500 trabajos. El procesador selecciona trabajos `email` pendientes cuya fecha `available_at` ya llegó, sin limitarse a un tenant.

Los parámetros se pueden ajustar en `config/app.php`, dentro de su array de configuración:

```php
'notifications' => [
    'email' => [
        'max_attempts' => 5,
        'retry_delay_seconds' => 300,
    ],
],
```

El intervalo antes del siguiente intento es `retry_delay_seconds × attempts`: con los valores predeterminados, 5, 10, 15 y 20 minutos antes de los reintentos. Los estados son `pending`, `processing`, `sent` y `failed`; `last_error` conserva el último error y `sent_at` la fecha de envío aceptado por SMTP.

Use `EmailQueueProcessor`, no el procesador genérico `NotificationQueueService::processNotificationBatch()`, para conservar el filtrado por canal y esta política de reintentos. El modelo estándar recupera reservas expiradas y rechaza confirmaciones de generaciones anteriores; consulte [reservas y recuperación](notificaciones.md#reservas-y-recuperación-de-la-cola), incluidos duración, actualización de workers y contratos personalizados. `data.lost` indica pérdida de reserva y no confirma envío. Una caída después de que SMTP acepte el mensaje puede provocar un envío duplicado al recuperar el trabajo; esta recuperación no garantiza entrega exactamente una vez.

## Ampliación e integración

`EmailNotificationTransport` y `EmailQueueProcessor` son clases finales. Para cambiar el proveedor de correo, implemente `GFrame\Mail\Contracts\MailSender` y páselo a `new EmailNotificationTransport($sender)`. Puede inyectar ese transporte, junto con un `NotificationQueueRepository`, en `new EmailQueueProcessor($repository, $transport)`. Para otro canal, implemente `NotificationTransport` y regístrelo con su propio identificador; no modifique las clases del paquete.

Las plantillas publicadas son archivos administrados por el actualizador. Cree una plantilla con otro ID para el contenido propio del proyecto y páselo en `template`; consulte [Correo y plantillas](mail.md) para sus variables y personalización visual. [Campañas](notification-campaigns.md) utiliza este transporte para el canal de correo y gestiona por separado audiencias, programación e historial.
