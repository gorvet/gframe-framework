# Transporte de correo para notificaciones

`notifications-email` conecta el sistema de notificaciones con `GFrame\Mail\MailService`. No implementa otro motor SMTP, no guarda credenciales y no contiene campañas ni notificaciones inbox.

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

## Cola y reintentos

Configure el cron del servidor para ejecutar `php bin/gframe-cron.php` al menos una vez por minuto. En cada ejecución, el registrador del addon asegura la tarea recurrente `notifications.email.queue`; `CronScheduler` la ejecuta cada 60 segundos. El procesador reserva exclusivamente filas del canal `email`. Los intentos fallidos vuelven a `pending` con una nueva fecha `available_at` hasta alcanzar `notifications.email.max_attempts` (5 por defecto). Después quedan en estado `failed`. El número de intentos se incrementa al reservar la fila, una sola vez por envío.

También puede invocarse `EmailQueueProcessor` con un repositorio y un transporte inyectados para pruebas o para un trabajador propio. `NotificationQueueWorker::runProcessorClass(EmailQueueProcessor::class)` funciona con las dependencias predeterminadas. No llame a `sendTemplateAsync()` desde este procesador: la cola ya proporciona la ejecución diferida y debe conocer si el envío SMTP tuvo éxito para decidir el reintento.
