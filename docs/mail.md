# Soporte Mail

El soporte Mail del núcleo realiza todos los envíos de correo de GFrame. Puede usarse directamente desde formularios, autenticación y servicios. El addon `notifications-email` lo utiliza como adaptador para colas y campañas.

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

```php
$result = (new MailService())->sendTemplateAsync(
    'user@example.com',
    'Bienvenido',
    'welcome',
    ['title' => 'Bienvenido', 'message' => 'Tu cuenta está lista.']
);
```

`sendTemplateAsync()` y `sendHtmlAsync()` entregan una closure serializable al ejecutor `Async`, basado en Opis Closure. El worker carga nuevamente la aplicación, lee SMTP desde `.env` y realiza el envío fuera de la petición web. La respuesta inmediata usa `mail_queued` o `mail_queue_failed`.

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
