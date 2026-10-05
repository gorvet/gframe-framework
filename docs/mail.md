# Correo y plantillas

El soporte Mail del núcleo realiza todos los envíos de correo de GFrame. Puede usarse directamente desde formularios, autenticación y servicios. El addon `notifications-email` lo utiliza como adaptador para colas y campañas.

## Elegir el método

| Método de `MailService` | Contenido | Ejecución |
| --- | --- | --- |
| `sendTemplateAsync()` | Plantilla y variables | Segundo plano; recomendado en acciones web |
| `sendHtmlAsync()` | HTML preparado por la aplicación | Segundo plano |
| `sendTemplate()` | Plantilla y variables | Espera al SMTP en el proceso actual |
| `sendHtml()` | HTML preparado por la aplicación | Espera al SMTP en el proceso actual |

Las variantes síncronas sirven también para workers y tareas programadas que ya se ejecutan fuera de la petición web. No vuelvas a encolar un envío dentro de un worker solo para hacerlo asíncrono.

## Configuración SMTP

Las credenciales se leen exclusivamente desde `.env`:

```dotenv
MAIL_HOST=smtp.example.com
MAIL_PORT=465
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Mi aplicación"
```

Para STARTTLS utiliza `MAIL_PORT=587` y `MAIL_ENCRYPTION=tls`; para TLS implícito, `465` y `ssl`, según el proveedor. El remitente debe estar autorizado por ese servidor. En contacto, mantén el remitente del proyecto y coloca al visitante en `reply_to`; no suplantes su dirección en `MAIL_FROM_ADDRESS`.

La configuración valida que existan host y remitente válido; no prueba la conexión ni las credenciales hasta enviar. Mantén `.env` fuera de Git y no muestres contraseñas ni errores internos de SMTP al visitante.

## Envío HTML directo

```php
use GFrame\Mail\MailService;

$result = (new MailService())->sendHtml(
    'lead@example.com',
    'Nuevo contacto',
    '<p>Se recibió un nuevo contacto.</p>',
    ['reply_to' => 'lead@example.com', 'reply_name' => 'Nombre del contacto']
);
```

El resultado contiene `status` y `code`. La aplicación decide si muestra una vista de error, `swalAlert` o `alertToast`.

## Envío en segundo plano

El ejemplo usa una plantilla propia llamada `welcome`, que debes crear antes en `app/views/templates/mail/welcome.html`. Para las plantillas incluidas, consulta sus nombres y variables más abajo.

```php
$result = (new MailService())->sendTemplateAsync(
    'user@example.com',
    'Bienvenido',
    'welcome',
    ['title' => 'Bienvenido', 'message' => 'Tu cuenta está lista.']
);
```

`sendTemplateAsync()` y `sendHtmlAsync()` entregan una closure serializable al ejecutor `Async`, basado en Laravel Serializable Closure. El worker carga nuevamente la aplicación, lee SMTP desde `.env` y realiza el envío fuera de la petición web. La respuesta inmediata usa `mail_queued` o `mail_queue_failed`.

Consulta [Async](async.md) para ejecutar otras tareas PHP en segundo plano y revisar los requisitos del worker.

El worker crea una nueva instancia de MailService: los objetos de configuración o registro de plantillas inyectados en la instancia que encola no se trasladan al worker. Las plantillas del envío asíncrono deben estar disponibles en el directorio estándar del proyecto y su SMTP en el entorno del worker.

### Resultado y feedback

| Código | Significado |
| --- | --- |
| `mail_queued` | Se aceptó el lanzamiento de la tarea; aún no confirma envío SMTP |
| `mail_queue_failed` | No se pudo crear o lanzar la tarea |
| `mail_sent` | El servidor SMTP aceptó el mensaje |
| `mail_send_failed` | Falló el envío SMTP |
| `mail_template_failed` | No se pudo renderizar la plantilla |
| `invalid_email` | Destinatario inválido en el envío síncrono |
| `mail_host_not_configured`, `mail_sender_not_configured` | Falta configuración válida |

En modo asíncrono, la plantilla y el destinatario se procesan en el worker: esos fallos posteriores no cambian la respuesta `mail_queued`. Valida el formulario en el controlador antes de encolar. El frontend debe agradecer la solicitud mediante `swalAlert` conforme al contrato de [Frontend core](frontend-core.md), sin afirmar que el destinatario ya recibió el correo.

Los fallos posteriores se registran en el log PHP con el prefijo `[GFrame Mail Async]`. Estos métodos no incluyen reintentos automáticos ni un estado consultable por ID de tarea. Las campañas disponen de su propio historial y mecanismo de procesamiento.

### Opciones de envío

`recipient_name` identifica al destinatario; `reply_to` y `reply_name` definen a quién responder; `timeout` limita la espera SMTP en segundos, con 60 por defecto y mínimo 1. `rate_limit` solicita la protección del formulario público descrita abajo. La API actual recibe un destinatario por llamada y no ofrece parámetros de adjuntos, CC o BCC.

PHPAsync requiere `laravel/serializable-closure:^1.3`. Antes de actualizar desde una versión que utiliza Opis Closure, deja finalizar los workers existentes: los formatos serializados son distintos. Actualiza el proyecto mediante `composer update gorvet/gframe --with-all-dependencies` cuando la versión corregida esté disponible. Una respuesta de cola confirma el inicio de la tarea, no la entrega del correo.

## Límite para formularios públicos de correo

`mail.rate_limit` es una protección antispam para formularios públicos que envían correo, como contacto o consultas. No es un límite global del servicio Mail ni debe utilizarse para los envíos internos de la aplicación: notificaciones, campañas, correos transaccionales, verificación de cuentas o recuperación de contraseñas.

El formulario público solicita esta protección incluyendo `rate_limit` en las opciones de su llamada a Mail. Los usos internos deben omitir esa opción. `MAIL_RATE_LIMIT_ENABLED=true` solo habilita la protección para las llamadas que la solicitan; no limita automáticamente los demás correos de la aplicación. La protección complementa el honeypot y la validación del formulario.

```dotenv
MAIL_RATE_LIMIT_ENABLED=true
MAIL_RATE_LIMIT_MAX_ATTEMPTS=5
MAIL_RATE_LIMIT_WINDOW_SECONDS=3600
```

Las variables sobrescriben los valores de `mail.rate_limit` en la configuración del proyecto. La cantidad y la ventana deben ser enteros positivos. Para desactivar el límite durante las pruebas, usa `MAIL_RATE_LIMIT_ENABLED=false`. Sin estas variables, el valor predeterminado es de cinco envíos por hora para las llamadas que lo soliciten.

```php
$result = (new \GFrame\Mail\MailService())->sendTemplateAsync(
    'equipo@example.com',
    'Contacto',
    'contactTemplate',
    ['title' => 'Contacto', 'name' => $name, 'subject' => $subject, 'message' => $message],
    ['reply_to' => $email, 'rate_limit' => ['scope' => 'contact', 'identity' => $clientAddress]]
);
```

El controlador del formulario público obtiene `$clientAddress` de la dirección del cliente conocida por el servidor, por ejemplo `$_SERVER['REMOTE_ADDR']`. No acepta una identidad enviada por el formulario ni confía directamente en `X-Forwarded-For`; detrás de un proxy debe configurarse la resolución de la IP real mediante proxies de confianza. Cada combinación de ámbito e identidad tiene su propio cupo.

El límite usa una ventana móvil, con archivos protegidos por bloqueo exclusivo en `storage/mail-rate/`. Las identidades se incluyen en una clave HMAC con `APP_KEY`, sin guardarse en texto claro. Las solicitudes concurrentes comparten el cupo. Con varios servidores, este directorio debe compartirse y admitir `flock`; el almacenamiento independiente limita cada servidor por separado.

La respuesta de bloqueo usa `mail_rate_limited`, con `data.retry_after` expresado en segundos hasta que vuelva a haber cupo, incluso si se reduce la cantidad configurada durante la ventana. Los fallos de envío o de creación de la tarea liberan el cupo. En modo asíncrono se cuenta la aceptación de la tarea; un fallo SMTP posterior del worker no devuelve ese cupo. El worker no consume un segundo cupo. La misma opción funciona con `sendTemplate()`, `sendHtml()` y `sendHtmlAsync()`.

Una identidad vacía o parámetros no positivos devuelven `mail_rate_limit_invalid`; si el almacenamiento no está disponible o está dañado, se devuelve `mail_rate_limit_unavailable` sin enviar. Conserva el honeypot y las validaciones del formulario en el controlador, antes de llamar a Mail: son protecciones complementarias y no consumen cupo.

Async selecciona y comprueba un ejecutable PHP CLI, nunca el binario de Apache o PHP-FPM. Busca en el runtime, junto al `php.ini` cargado y en `PATH`; `GFRAME_PHP_BINARY` permite indicar explícitamente su ruta absoluta. Si no encuentra CLI o no puede lanzar el proceso, devuelve un fallo de encolado. El SMTP continúa ejecutándose en el worker, no en la petición. Campañas mantiene su cola y cron de correo independientes.

Las plantillas estándar de acceso, contacto y notificación incluyen el logo y un pie pequeño fuera del card. El pie base utiliza el año actual (`mailYear`), el nombre del proyecto, los derechos reservados y «Construido con GFrame»; una plantilla personalizada puede sustituirlo por sus créditos. El logo utiliza `mailLogo`: para verlo desde un cliente de correo remoto, la URL debe ser accesible públicamente; `localhost` no lo es.

Las tres comparten los estilos de `MailThemeHelper`, resueltos al renderizar por `MailTemplateRegistry`: fuente, fondo, texto, card, títulos, botones y pie. Notificaciones utiliza los mismos placeholders de tema que acceso y contacto, sin colores ni tipografía independientes. Su acción se oculta con `action_display` cuando no existe enlace y su mensaje conserva los saltos de línea. El contenido funcional puede variar: contacto no necesita un CTA artificial. La homogeneidad se comprueba con `MailTemplatesTest` y el fixture `tests/fixtures/mail-templates-preview.php`.

Todas las plantillas estándar incluyen saludo y pie. El motor completa `greeting` con `recipient_name` o `user_name`, y utiliza «Hola.» si no hay nombre disponible. `MailService` toma el nombre de las opciones, del contexto del destinatario o, como último recurso, de la parte local del correo. No usa el nombre del remitente del formulario como nombre del destinatario. En contacto y Notificaciones, `greeting_display` oculta el saludo añadido cuando `message` ya empieza con un saludo reconocido, conservando el mensaje original sin duplicarlo. Un `greeting` explícito tiene prioridad. No se altera automáticamente HTML enviado mediante `sendHtml()`.

## Envío mediante plantilla

```php
$result = (new MailService())->sendTemplate(
    'user@example.com',
    'Bienvenido',
    'welcome',
    ['title' => 'Bienvenido', 'message' => 'Tu cuenta está lista.']
);
```

La plantilla se resuelve como `app/views/templates/mail/welcome.html`. Puede acompañarse con `welcome.json` para declarar su nombre y variables. Los valores se escapan antes de insertarse.

## Tema

`MailThemeHelper` incorpora variables como `mailSiteName`, `mailLogo`, `mailPrimary`, `mailFont` y estilos para botones, títulos y contenido. Sus valores se derivan de `public/css/variables.css` cuando existe.

El helper vuelve a leer el archivo al generar cada correo. Toma declaraciones globales de `:root` o `html` y las de `:root[data-bs-theme="light"]` o `html[data-bs-theme="light"]`; las claras explícitas prevalecen. Ignora reglas oscuras, de componentes y bloques anidados como `@media`. No ejecuta un navegador ni interpreta toda la cascada CSS: resuelve referencias simples `var(--nombre)` y usa valores predeterminados para `color-mix()` o referencias cíclicas. El correo usa una paleta clara definida, independiente del tema elegido en el navegador del remitente.

Los estilos se incorporan al HTML al generarlo, no se enlaza una hoja CSS externa. Un correo ya enviado no cambia cuando se edita `variables.css`. El cliente de correo puede aplicar su propio modo oscuro. Puedes sobrescribir los parámetros `mail*` al renderizar, por ejemplo el logo o el color de marca.

## Plantillas comunes incluidas

El esqueleto publica las dos plantillas originales adaptadas en `app/views/templates/mail/`:

- `mailTemplate`: mensaje con título, saludo y enlace de acción. Variables de contenido: `title`, `h1`, `greeting`, `p1`, `aHref`, `aText` y `p2`.
- `contactTemplate`: mensaje de un formulario de contacto. Variables de contenido: `title`, `name`, `subject` y `message`.

```php
$result = (new \GFrame\Mail\MailService())->sendTemplateAsync(
    'usuario@example.com',
    'Verifica tu cuenta',
    'mailTemplate',
    [
        'title' => 'Verificación', 'h1' => 'Verifica tu cuenta',
        'greeting' => 'Hola, Ana.',
        'p1' => 'Confirma tu correo mediante este enlace.',
        'aHref' => $verificationUrl, 'aText' => 'Verificar',
        'p2' => 'Si no solicitaste esta cuenta, ignora el mensaje.',
    ]
);
```

Para contacto, usa `contactTemplate` y envía `name`, `subject` y `message`, además de `title`. Configura `reply_to` en las opciones del envío después de validar el correo del visitante. El destinatario se decide en tu aplicación; no se toma del formulario sin validar.

Los valores de contenido se tratan como texto y se escapan, no como HTML libre. El mensaje de contacto conserva saltos mediante `white-space:pre-wrap`, sujeto al soporte del cliente de correo. Los marcadores aceptan `{{nombre}}` y `{{ nombre }}` y se sustituyen en una sola pasada. Las URL de acciones y logos deben construirse o validarse en la aplicación, usando HTTP/HTTPS; escapar HTML no valida el protocolo de una URL. No pases estilos `mail*` procedentes de entradas sin validar del usuario.

Estas plantillas quedan disponibles para cualquier llamada a Mail. El controlador de `auth-ui` utiliza `mailTemplate` para registro, reenvío de verificación y recuperación de contraseña, con saludo personalizado y una acción específica. No añaden un formulario ni rutas de contacto. `notifications-email` publica su plantilla específica y utiliza el mismo motor.

`composer gframe:update` actualiza también todo el directorio `app/views/templates/mail` del esqueleto, incluidos HTML y metadatos; no basta con actualizar el paquete Composer. `--dry-run` permite revisar los reemplazos sin escribir. Por defecto los archivos publicados son gestionados; `--preserve-custom` conserva las modificaciones y las informa como conflictos, por lo que una plantilla antigua conservada no recibirá las mejoras hasta resolver ese conflicto. Las pruebas de actualización verifican el HTML renderizado desde el proyecto actualizado, además de los archivos fuente.

No se mantiene una fachada heredada. Al actualizar una aplicación, sus llamadas antiguas a `Email` deben migrarse a `GFrame\Mail\MailService`.

## Prueba SMTP real optativa

`tests/fixtures/smtp-live.php` prueba el servicio del framework utilizando el `.env` de un directorio elegido. No modifica ese archivo ni guarda credenciales; requiere `--send`, destinatario explícito y puerto 465 o 587. Envía un correo real de prueba y muestra únicamente puerto, estado, código y duración.

```powershell
php tests/fixtures/smtp-live.php --send C:\ruta\configuracion usuario@example.com 587
php tests/fixtures/smtp-live.php --send C:\ruta\configuracion usuario@example.com 465
```

La configuración de puerto/cifrado se cambia solo en memoria: 465 utiliza SSL/TLS implícito y 587 STARTTLS. No se desactiva la comprobación TLS. `mail_sent` confirma aceptación por el servidor SMTP, no entrega en la bandeja del destinatario. Esta prueba no se ejecuta en la suite automática ni valida la cola, el cron o las plantillas de cada aplicación.
