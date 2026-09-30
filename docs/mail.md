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

- `mailTemplate`: mensaje con título y enlace de acción. Variables de contenido: `title`, `h1`, `p1`, `aHref`, `aText` y `p2`.
- `contactTemplate`: mensaje de un formulario de contacto. Variables de contenido: `title`, `name`, `subject` y `message`.

```php
$result = (new \GFrame\Mail\MailService())->sendTemplateAsync(
    'usuario@example.com',
    'Verifica tu cuenta',
    'mailTemplate',
    [
        'title' => 'Verificación', 'h1' => 'Verifica tu cuenta',
        'p1' => 'Confirma tu correo mediante este enlace.',
        'aHref' => $verificationUrl, 'aText' => 'Verificar',
        'p2' => 'Si no solicitaste esta cuenta, ignora el mensaje.',
    ]
);
```

Para contacto, usa `contactTemplate` y envía `name`, `subject` y `message`, además de `title`. Configura `reply_to` en las opciones del envío después de validar el correo del visitante. El destinatario se decide en tu aplicación; no se toma del formulario sin validar.

Los valores de contenido se tratan como texto y se escapan, no como HTML libre. El mensaje de contacto conserva saltos mediante `white-space:pre-wrap`, sujeto al soporte del cliente de correo. Los marcadores aceptan `{{nombre}}` y `{{ nombre }}` y se sustituyen en una sola pasada. Las URL de acciones y logos deben construirse o validarse en la aplicación, usando HTTP/HTTPS; escapar HTML no valida el protocolo de una URL. No pases estilos `mail*` procedentes de entradas sin validar del usuario.

Estas plantillas quedan disponibles para cualquier llamada a Mail; no se enlazan automáticamente a los flujos de autenticación ni añaden un formulario o rutas de contacto. Las aplicaciones conectan sus servicios mediante `sendTemplate()` o `sendTemplateAsync()`. `notifications-email` conserva su plantilla específica y usa el mismo motor. La compatibilidad visual con clientes de correo concretos requiere pruebas de entrega independientes.

No se mantiene una fachada heredada. Al actualizar una aplicación, sus llamadas antiguas a `Email` deben migrarse a `GFrame\Mail\MailService`.
