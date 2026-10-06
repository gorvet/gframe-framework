# Notificaciones

El módulo `notifications` proporciona un inbox por usuario, aislamiento opcional por tenant y un registro extensible de transportes. No contiene campañas ni lógica específica de correo, WhatsApp, Telegram, push o SMS.

## Instalación

Instale `notifications` mediante el [catálogo de módulos](modulos-opcionales.md). Sus controladores y vistas permanecen en el paquete; las rutas y los assets se publican en el proyecto. Si `admin-panel` está instalado, añade la campana a su encabezado. El esquema crea `user_notifications` y la infraestructura común de cola.

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

`notify()` guarda el aviso inmediatamente y devuelve su ID en `data.notification_id`; esta escritura no requiere un worker. Para envíos masivos o diferidos, utilice [Campañas](notification-campaigns.md).

| Campo | Comportamiento |
| --- | --- |
| `title` | Obligatorio; texto sin HTML, hasta 180 caracteres |
| `message` | Obligatorio; texto sin HTML, hasta 12 000 caracteres |
| `type` | Clasificación propia, por ejemplo `invoice.paid`; por defecto `system` |
| `importance` | `info`, `warning` o `danger`; por defecto `info` |
| `action_url` | HTTP/HTTPS o ruta local que comienza por `/`; un enlace inválido queda en `null` |
| `meta` | Array de datos adicionales, almacenado como JSON |
| `expires_at` | Fecha interpretable por PHP; omitida o inválida significa sin caducidad |

El servicio recorta los textos largos. `type` se normaliza a minúsculas y a caracteres alfanuméricos, puntos, guiones y guiones bajos. Los metadatos no añaden acciones automáticamente a la interfaz.

### Usuario y tenant

Pase el mismo `$tenantID` al crear y consultar un aviso. `null` selecciona exclusivamente los avisos sin tenant: no permite consultar todos los tenants ni mezcla sus avisos con los del ámbito global.

El controlador obtiene el usuario de `$_SESSION['auth']['id']` y el tenant de `$_SESSION['auth']['tenant_id']`, con compatibilidad para las claves anteriores. No acepta la identidad del destinatario desde el formulario. En servicios o comandos, proporcione explícitamente ambas identidades y compruebe la pertenencia del destinatario al ámbito; `notify()` no valida esa membresía.

### Consultar y cambiar el estado

```php
$inbox = $notifications->inbox($userID, 20, $tenantID);
$unreadCount = $inbox['data']['unread'] ?? 0;
$history = $notifications->history($userID, 1, 20, true, $tenantID);
$items = $history['data']['items'] ?? [];
$pagination = $history['meta'] ?? [];
$detail = $notifications->detail($notificationID, $userID, $tenantID);
$read = $notifications->markRead($notificationID, $userID, $tenantID);
$unread = $notifications->markUnread($notificationID, $userID, $tenantID);
```

`inbox()` ordena del más reciente al más antiguo y limita la entrega a entre 1 y 80 elementos. `history()` pagina y filtra solo los no leídos con su cuarto argumento. Su `meta` contiene `page`, `total_pages`, `total` y `per_page`; el tamaño de página también se limita a 80. `detail()` solo consulta: marcar como leído es una operación separada del servicio.

Un aviso ajeno, expirado o eliminado devuelve `notification_not_found`. Compruebe `status` antes de consumir `data`. La falta de título o mensaje devuelve `invalid_notification`; un usuario no positivo, `invalid_user` al crear o listar.

## Interfaz y rutas

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
use GFrame\Notifications\InboxNotificationTransport;
use GFrame\Notifications\NotificationTransportRegistry;

$registry = new NotificationTransportRegistry();
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

`dispatch()` ejecuta el transporte en ese mismo proceso; el registro no crea una cola por sí solo. Su resultado confirma la ejecución del transporte y no aporta el ID del aviso. Para obtener ese ID, use directamente `notify()`. La integración de [correo](notifications-email.md) y las colas tienen sus propios contratos.

## Extensión

Notificaciones conserva sus originales en `resources/modules/notifications/application/app`. El instalador crea vacías `app/controllers/notifications` y `app/views/notifications`, las capas presentes en el módulo; no copia las clases ni las vistas originales. Declare `module('notifications')` en las rutas; sus URLs y nombres de vistas permanecen iguales.

El controlador propio se guarda en `app/controllers/notifications/NotificationController.php`, con namespace `App\Controllers\Notifications`, y extiende `GFrame\Modules\Notifications\Controllers\NotificationController`. Los modelos y servicios propios usan `App\Models\Notifications` y `App\Services\Notifications`; pueden extender `GFrame\Notifications\NotificationModel` y `NotificationService`. Construya e inyecte su servicio mediante `parent::__construct(...)`. La existencia de una subclase no sustituye automáticamente una instancia del original.

Las vistas propias van en `app/views/notifications`: `notificationsIndex.php`, `notificationsView.php`, `_history.php`, `_inbox.php`, `_detailModal.php` y `notifications.group.meta.php`. La campana se personaliza en `app/views/notifications/parts/bell.php`; el fragmento publicado en el header solo la resuelve e incluye. Carga inicial, AJAX, modal y heartbeat usan la misma prioridad del proyecto sobre el módulo. CSS y JS mantienen sus rutas publicadas actuales.

Traslade expresamente las personalizaciones antiguas de `app/views/components/notifications` a `app/views/notifications`. Los archivos ya existentes en `app/controllers/notifications` y `app/views/notifications` siguen teniendo prioridad, incluso si eran copias originales de una instalación anterior: retírelos manualmente si desea usar el original del paquete, o conviértalos en sus personalizaciones. El actualizador no borra esos archivos.

Una aplicación puede sustituir la persistencia implementando `NotificationRepository`. No debe modificar los archivos administrados del módulo. Las reglas de negocio se mantienen en servicios externos que consumen `NotificationService` o el registro de transportes.

Por ejemplo, cree `app/services/notifications/ProjectNotificationService.php`:

```php
<?php
namespace App\Services\Notifications;

use GFrame\Notifications\NotificationService;

class ProjectNotificationService extends NotificationService
{
    public function invoicePaid(int $userID, int $invoiceID, ?int $tenantID): array
    {
        return $this->notify($userID, [
            'type' => 'invoice.paid',
            'title' => 'Factura pagada',
            'message' => 'El pago de tu factura ha sido confirmado.',
            'meta' => ['invoice_id' => $invoiceID],
        ], $tenantID);
    }
}
```

Instáncielo en su servicio de facturación con `new ProjectNotificationService(new NotificationModel())` y llame a `invoicePaid()` tras confirmar el pago. Para utilizarlo también en el controlador, cree `app/controllers/notifications/NotificationController.php`:

```php
<?php
namespace App\Controllers\Notifications;

use App\Services\Notifications\ProjectNotificationService;
use GFrame\Notifications\NotificationModel;

class NotificationController extends \GFrame\Modules\Notifications\Controllers\NotificationController
{
    public function __construct()
    {
        parent::__construct(new ProjectNotificationService(new NotificationModel()));
    }
}
```

Al inyectar un servicio, el controlador deja de procesar automáticamente la cola inbox estándar durante las consultas. Mantenga un procesador de cola independiente si usa avisos encolados. Las llamadas directas a `notify()` siguen siendo inmediatas.

## Caducidad y limpieza

Los avisos expirados desaparecen de las consultas y del contador sin esperar una limpieza. `$notifications->cleanup()` marca lógicamente los expirados de todos los usuarios y tenants; no elimina filas físicamente y no recibe un ámbito. Ejecútelo desde una tarea de mantenimiento, no desde una acción de usuario. Consulte las [operaciones de limpieza](limpieza.md).

Los adaptadores existentes deben añadir `findNotification(int $notificationID, int $userID, ?int $tenantID): ?array` y `markUnread(int $notificationID, int $userID, ?int $tenantID): bool`. Ambos respetan el mismo ámbito y vigencia que `markRead`; marcar como no leída deja `read_at` en `null`. El modelo estándar ya incluye estas operaciones; no cambia el esquema.

Campañas es un módulo separado que resuelve audiencias y crea trabajos de envío. El registro de transportes se conserva para conectar capacidades externas, no para personalizar vistas o controladores. La personalización MVC utiliza herencia PHP, sin fábricas ni callbacks adicionales.
