# Notificaciones

El módulo `notifications` proporciona un inbox por usuario, aislamiento opcional por tenant y un registro extensible de transportes. No contiene campañas ni lógica específica de correo, WhatsApp, Telegram, push o SMS.

## Instalación

El catálogo publica el controlador, las rutas web y AJAX, el parcial del inbox, la página de historial, CSS y JavaScript. Si `admin-panel` está instalado, el módulo añade un control a su encabezado. El esquema crea `user_notifications` y conserva la infraestructura común de cola para transportes asíncronos.

## Crear una notificación

```php
use GFrame\Notifications\NotificationModel;
use GFrame\Notifications\NotificationService;

$notifications = new NotificationService(new NotificationModel());
$result = $notifications->notify($userID, [
    'type' => 'account.updated',
    'importance' => 'info',
    'title' => 'Cuenta actualizada',
    'message' => 'Los cambios fueron guardados.',
    'action_url' => '/account',
    'meta' => ['source' => 'profile'],
    'expires_at' => '+30 days',
], $tenantID);
```

Todas las operaciones devuelven `status` y `code`; según la operación también incluyen `message`, `data`, `meta` o `html`. Los errores internos se capturan con `Exception` y no se exponen directamente al usuario.

## Inbox

Una vista puede incluir `app/views/components/notifications/_inbox.php` dentro de un contenedor con `data-notifications-inbox`. El botón para marcar todas utiliza `data-notifications-mark-all`.

Las rutas autenticadas disponibles son:

- `POST ajax/notifications/inbox`;
- `POST ajax/notifications/history`;
- `POST ajax/notifications/mark-read`;
- `POST ajax/notifications/mark-all-read`;
- `POST ajax/notifications/delete`.

El usuario solo puede consultar o modificar sus propias notificaciones dentro de su tenant. La eliminación es lógica y las notificaciones expiradas se excluyen antes de aplicar el límite o paginar. El contador muestra todas las no leídas vigentes del ámbito, no solo las que caben en el desplegable. El enlace de acción aparece únicamente si es HTTP, HTTPS o una ruta local segura.

La ruta autenticada `GET notifications` muestra el historial paginado con filtros «Todas» y «No leídas». El desplegable enlaza a esta página. Ambas vistas permiten marcar avisos como leídos o eliminarlos; la página también permite marcarlos todos. La carga AJAX reemplaza únicamente el fragmento de resultados renderizado por PHP y mantiene página y filtro en la URL.

## Heartbeat

Cuando `heartbeat-client` detecta el controlador publicado, registra `notifications.inbox`. La respuesta incluye el HTML renderizado en PHP, los elementos activos y el total no leído.

## Transportes

`NotificationTransportRegistry` desacopla el canal de su implementación. El inbox se registra así:

```php
$registry->register('inbox', new InboxNotificationTransport($notifications));
$result = $registry->dispatch('inbox', [
    'recipient' => $userID,
    'tenant_id' => $tenantID,
    'payload' => [
        'title' => 'Aviso',
        'message' => 'Contenido',
    ],
]);
```

Cada addon implementa `NotificationTransport` y registra su propio canal. `notifications-email`, WhatsApp, Telegram, push y SMS permanecen separados de este módulo.

## Extensión

Una aplicación puede sustituir la persistencia implementando `NotificationRepository`. No debe modificar los archivos administrados del módulo. Las reglas de negocio se mantienen en servicios externos que consumen `NotificationService` o el registro de transportes.

Las campañas serán otro módulo. Su única responsabilidad será resolver audiencias y realizar envíos masivos mediante los transportes registrados.
