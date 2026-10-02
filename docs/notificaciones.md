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

Una vista puede resolver `notifications/_inbox.php` con `ModuleRuntime::file('views', 'notifications/_inbox.php', 'notifications')` e incluirlo dentro de un contenedor con `data-notifications-inbox`. Se utiliza primero la personalización en `app` y después el original del módulo. La interfaz no incluye «Marcar todas», borrado ni archivo; los endpoints anteriores se conservan por compatibilidad.

Las rutas autenticadas disponibles son:

- `POST ajax/notifications/inbox`;
- `POST ajax/notifications/history`;
- `POST ajax/notifications/detail`;
- `POST ajax/notifications/mark-read`;
- `POST ajax/notifications/mark-unread`;
- `POST ajax/notifications/mark-all-read`;
- `POST ajax/notifications/delete`.

El usuario solo puede consultar o modificar sus propias notificaciones dentro de su tenant. La eliminación es lógica y las notificaciones expiradas se excluyen antes de aplicar el límite o paginar. El contador muestra todas las no leídas vigentes del ámbito, no solo las que caben en el desplegable. El enlace de acción aparece únicamente si es HTTP, HTTPS o una ruta local segura.

La ruta autenticada `GET notifications` muestra el listado con título y espaciado del admin y filtros «Todas» y «No leídas», también disponibles en la campana. Solo se muestra la paginación común cuando existe más de una página. Cada fila contiene un icono de importancia, resumen de dos líneas y punto únicamente si no está leída. El menú de tres puntos permite marcar como leída o no leída; no ofrece borrado ni archivo. Se conserva la API anterior de eliminación lógica por compatibilidad, no como acción de la interfaz.

Al pulsar una fila se envía un POST con CSRF a `ajax/notifications/detail`; el controlador consulta el aviso, lo marca como leído y devuelve en `html` el modal renderizado en PHP. El frontend abre el modal sin navegar a otra pantalla y actualiza contador y listados. La consulta y el cambio de estado verifican usuario, tenant, caducidad y eliminación. El mensaje completo y el enlace de acción seguro aparecen dentro del modal. Se conserva `GET notifications/view?id=ID` como enlace alternativo si JavaScript no está disponible; el GET no modifica datos.

El resumen muestra tiempo relativo compacto (`1 s`, `2 h`, `6 d`), más pequeño y en color primario. Se calcula con la zona horaria del proyecto, igual que `created_at`; los datos originales no cambian y la fecha exacta queda en el atributo `title`. La carga AJAX y heartbeat actualizan ese tiempo al renovar el HTML. El relleno de cada fila incluye su fondo resaltado, tanto en la campana como en la tarjeta del listado.

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

Notificaciones conserva sus originales en `resources/modules/notifications/application/app`. El instalador crea vacías `app/{controllers,models,services,views}/notifications`; no copia las clases ni las vistas originales. Declare `module('notifications')` en las rutas; sus URLs y nombres de vistas permanecen iguales.

El controlador propio se guarda en `app/controllers/notifications/NotificationController.php`, con namespace `App\Controllers\Notifications`, y extiende `GFrame\Modules\Notifications\Controllers\NotificationController`. Los modelos y servicios propios usan `App\Models\Notifications` y `App\Services\Notifications`; pueden extender `GFrame\Notifications\NotificationModel` y `NotificationService`. Construya e inyecte su servicio mediante `parent::__construct(...)`. La existencia de una subclase no sustituye automáticamente una instancia del original.

Las vistas propias van en `app/views/notifications`: `notificationsIndex.php`, `notificationsView.php`, `_history.php`, `_inbox.php`, `_detailModal.php` y `notifications.group.meta.php`. La campana se personaliza en `app/views/notifications/parts/bell.php`; el fragmento publicado en el header solo la resuelve e incluye. Carga inicial, AJAX, modal y heartbeat usan la misma prioridad del proyecto sobre el módulo. CSS y JS mantienen sus rutas publicadas actuales.

Traslade expresamente las personalizaciones antiguas de `app/views/components/notifications` a `app/views/notifications`. Los archivos ya existentes en `app/controllers/notifications` y `app/views/notifications` siguen teniendo prioridad, incluso si eran copias originales de una instalación anterior: retírelos manualmente si desea usar el original del paquete, o conviértalos en sus personalizaciones. El actualizador no borra esos archivos.

Una aplicación puede sustituir la persistencia implementando `NotificationRepository`. No debe modificar los archivos administrados del módulo. Las reglas de negocio se mantienen en servicios externos que consumen `NotificationService` o el registro de transportes.

Los adaptadores existentes deben añadir `findNotification(int $notificationID, int $userID, ?int $tenantID): ?array` y `markUnread(int $notificationID, int $userID, ?int $tenantID): bool`. Ambos respetan el mismo ámbito y vigencia que `markRead`; marcar como no leída deja `read_at` en `null`. El modelo estándar ya incluye estas operaciones; no cambia el esquema.

Campañas es un módulo separado que resuelve audiencias y crea trabajos de envío. El registro de transportes se conserva para conectar capacidades externas, no para personalizar vistas o controladores. La personalización MVC utiliza herencia PHP, sin fábricas ni callbacks adicionales.
