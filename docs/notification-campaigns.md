# Campañas de notificaciones

`notification-campaigns` crea envíos masivos inmediatos o programados. Depende de `notifications` y `cron-runner`, pero no depende de ningún transporte concreto.

## Responsabilidades

El módulo administra:

- contenido y estado de la campaña;
- canales seleccionados;
- audiencia y variables por destinatario;
- programación mediante `cron-runner`;
- creación de trabajos en `notification_queue`;
- pausa, reanudación, cancelación y progreso.

No envía correos ni mensajes directamente. Cada addon procesa los trabajos de su canal.

## Flujo

```text
campaña -> cron-runner -> notification_queue -> transporte registrado
```

Una campaña inmediata procesa su primer lote al crearse. Si quedan destinatarios, la tarea cron continúa procesando lotes. Una campaña programada espera hasta `scheduled_at`.

## Audiencias

La interfaz administrativa permite seleccionar todos los usuarios activos, solo administradores activos o usuarios concretos mediante GFSelect. Excluye cuentas sin verificar, desactivadas y suspendidas. En un tenant, exige además una membresía activa. La comprobación se repite al procesar cada destinatario; una cuenta que deje de ser elegible queda marcada como fallida con `recipient_excluded`, sin generar un trabajo nuevo.

El formulario deja de aceptar la lista de direcciones libres en `recipients`: utiliza `audience` (`active`, `administrators` o `manual`) y `user_ids[]` para la selección manual. Para integrar audiencias de una aplicación, implemente `CampaignAudienceProvider`:

```php
final class CustomerAudience implements CampaignAudienceProvider
{
    public function recipients(array $criteria): iterable
    {
        yield [
            'recipients' => ['email' => 'user@example.com', 'inbox' => '42'],
            'variables' => ['name' => 'Ana'],
        ];
    }
}
```

El servicio acepta el proveedor sin conocer las tablas de usuarios, clientes o suscriptores del proyecto.

El controlador y el cron instalados utilizan `CampaignUserAudience`, correspondiente al esquema de autenticación estándar. Un proyecto con otra audiencia debe configurar sus propios adaptadores y su `CampaignRecipientGuard` en ambos puntos. El cuarto argumento opcional de `CampaignService` recibe ese guard; los servicios construidos con los tres argumentos anteriores mantienen su comportamiento.

## Campañas automáticas

La vista independiente `admin/notifications/campaigns/automatic` y su enlace en la sección Campañas requieren `notifications.campaigns.manage`. Permite activar reglas, guardar cada una por separado y editar su contenido. Las reglas se guardan por ámbito en `notification_campaign_rules`; ámbito cero significa global. La migración se incluye en instalación y actualización.

Las reglas de suspensión, bloqueo y verificación empiezan desactivadas. El recordatorio de eliminación empieza activo para las nuevas configuraciones; se respetan las reglas ya guardadas. Gestión de usuarios emite el evento de suspensión después de una operación correcta, solo cuando está instalada la vista del módulo. El aviso usa correo porque la cuenta suspendida no puede acceder a su inbox. Encolar no confirma entrega; hace falta un transporte de correo y su trabajador.

`AutomaticCampaignDispatcher::emit($ruleKey, $userID, $eventID, $context, $tenantID)` admite eventos de suspensión, bloqueo, verificación y recordatorio previo a eliminación. El identificador del evento debe ser estable para sus reintentos; la cola evita duplicados. Comprueba estado, correo y pertenencia al tenant antes de encolar.

El listado abre la configuración de cada regla en un modal. «Enviar ahora» es una acción directa, sin modal ni selector: el backend consulta los destinatarios elegibles de la regla. El envío manual no exige que los automatismos estén activos. Usa el contenido guardado y comprueba nuevamente estado y pertenencia al ámbito, respetando el plazo sin duplicados. Los recordatorios de eliminación solo procesan cuentas con ciclo registrado y fecha real, en el ámbito global. El endpoint `automatic/send` conserva su ruta y `rule_key`; deja de utilizar los antiguos campos `user_ids[]` y `reason`.

El historial separado `admin/notifications/campaigns/automatic/history` tiene su enlace en Campañas y requiere el mismo permiso de gestión. `notification_automatic_campaign_history` registra cada destinatario junto a su trabajo de cola, dentro de la transacción de envío. Agrupa por ejecución y regla, conserva el título utilizado y distingue origen manual o automático. El listado muestra destinatarios, fecha UTC y entregas en cola, enviadas o fallidas según el transporte. Cambiar la regla o repetir una ejecución omitida no altera el historial. Incluye eventos, revisión periódica y el aviso inmediato de desactivación; no inventa registros para los envíos anteriores a instalar esta migración.

Guardar programa una revisión cada hora en `cron-runner`. Recorre las cuentas suspendidas, bloqueadas y sin verificar, y comparte con el evento inmediato y el envío manual la protección por ámbito, regla y usuario. Es necesario ejecutar el runner del proyecto para que funcione esta revisión; registrar una tarea no inicia un proceso de servidor.

«No repetir durante» admite de 1 a 3650 días; el valor inicial es 7. `notification_campaign_deliveries` conserva la fecha de encolado, el origen y el trabajo de cola por usuario y regla. La reserva condicionada y el encolado son transaccionales. Se omite cualquier solicitud dentro del plazo, aunque sea otro evento o cambie de automático a manual. Cambiar título o mensaje no evita el bloqueo. El resultado manual distingue añadidos, omitidos y no procesados. Esta protección comienza al encolar, para cubrir trabajos todavía pendientes; no significa entrega confirmada. Se aplica a estas reglas, no a una campaña normal independiente. No reconstruye retroactivamente los avisos creados antes de instalar este registro.

Bloqueo requiere que la aplicación use ese estado. La verificación reutiliza `AuthModel::verifyAcount` para renovar el token y construye el enlace original `login/verify?v=...`. Solo lo hace después de reservar el envío; los avisos omitidos no cambian tokens, y un fallo de cola revierte el token y la reserva. Los adaptadores pueden seguir proporcionando `verification_url` individual. El contexto también permite `reason` para avisos de bloqueo.

### Extender las reglas por herencia

El módulo conserva sus originales en `application/app`. Las carpetas de personalización son `app/controllers/notification-campaigns`, `app/models/notification-campaigns`, `app/services/notification-campaigns` y `app/views/notification-campaigns`. La actualización las crea vacías y no sobrescribe sus archivos. Las URLs no cambian.

El controlador personalizado usa `App\Controllers\NotificationCampaigns\CampaignController` y extiende `GFrame\Modules\NotificationCampaigns\Controllers\CampaignController`. Su constructor permite inyectar `CampaignModel`, `CampaignService`, `AutomaticCampaignModel` y `CampaignUserAudience`. Para ampliar reglas automáticas, cree un modelo de proyecto:

```php
<?php
namespace App\Models\NotificationCampaigns;

class AutomaticCampaignModel extends \GFrame\Notifications\Campaigns\AutomaticCampaignModel
{
    public function definitions(): array
    {
        return parent::definitions() + ['project.invoice_due' => [
            'name' => 'Factura pendiente',
            'description' => 'Recordatorio de facturas pendientes.',
            'title' => 'Factura {{invoice_number}} pendiente',
            'message' => 'Hola {{user_name}}, revisa tu factura.',
            'is_active' => 0, 'cooldown_days' => 7, 'periodic' => true,
        ]];
    }

    // Implemente también eligibleUsers(), eligible() y variables()
    // para la regla nueva; delegue las demás a parent.
}
```

`definitions()` devuelve contenido inicial, activación, plazo y recurrencia periódica. `eligibleUsers(string $key, int $scopeID = 0): array` devuelve usuarios con `user_id`. `eligible(string $key, array $user, array $context = [], ?int $tenantID = null): bool` vuelve a verificar elegibilidad antes de encolar. `variables(...): array` recibe los mismos argumentos y agrega valores para placeholders; no sobrescribe identidad, URLs ni contexto. Respete el ámbito, las validaciones y los efectos secundarios originales; conserve las reglas de cuenta delegando a `parent`.

En el controlador propio, use `parent::__construct(automaticModel: new \App\Models\NotificationCampaigns\AutomaticCampaignModel())`. Listado, editor y envío manual usarán ese modelo. Para la revisión periódica, cree una subclase de `AutomaticCampaignCronHandler` en `App\Services\NotificationCampaigns` que sobrescriba `protected function model(): AutomaticCampaignModel` y devuelva la misma clase de modelo. El controlador propio debe sobrescribir `protected function automaticCronHandler(): string` para devolver la clase de ese cron. Guardar una regla programa o reactiva esa clase; las tareas ya guardadas con el cron estándar deben actualizarse guardando la regla nuevamente. El runner no selecciona subclases automáticamente.

Los eventos propios pueden llamar a `AutomaticCampaignDispatcher::emit($key, $userID, $eventID, $context, $tenantID, false, $modeloPropio)`. El identificador debe ser estable para reintentos. Se mantienen activación, pertenencia al tenant, reserva transaccional, plazo sin repetir, cola e historial. Si se omite el modelo, se utilizan las reglas estándar.

Se retiraron `AutomaticCampaignRegistry` y sus callbacks. `config/notifications/automatic-campaigns.php` ya no se carga: traslade definiciones, audiencia, elegibilidad y variables a los métodos anteriores. No se borra ese archivo, ni las reglas guardadas, ni el historial del proyecto. No se añadió un sistema global de hooks.

### Vistas y migración

Declare las rutas con `notification-campaigns/CampaignController@acción`, `module('notification-campaigns')` y el template `admin`. Las vistas explícitas son `index`, `form`, `automatic` y `automaticHistory`. Antes estaban bajo `admin/notifications/campaigns`; ahora se personalizan en `app/views/notification-campaigns`. El controlador anterior `admin/notifications/CampaignController.php` pasa a `app/controllers/notification-campaigns/CampaignController.php`, con el namespace del proyecto.

Todos los parciales del módulo buscan primero en `app` y después en el original, tanto en la carga inicial como en AJAX. CSS y JS mantienen sus rutas publicadas `public/{css,js}/modules/notification-campaigns`. No cambian formularios, apariencia, permisos ni nombres de endpoints. Las personalizaciones anteriores deben trasladarse expresamente; el actualizador no las elimina.

### Cuentas desactivadas

Con `self-account` y `notification-campaigns` instalados, una desactivación voluntaria registra el ciclo y su fecha de eliminación en `notification_account_deactivations`, y encola un correo inmediato. La configuración `auth.deactivation.retention_days` vale 60 y `auth.deactivation.warning_hours` vale 72; no requiere una pantalla nueva. La fecha se guarda por ciclo: modificar la retención no recalcula las fechas existentes. Una nueva desactivación después de reactivar reinicia el plazo completo.

La revisión horaria prepara el aviso previo cuando faltan 72 horas. Si se ejecuta tarde, pospone la fecha para conservar el margen. El borrado exige simultáneamente que la cuenta siga desactivada, que venza su fecha y que el correo previo figure enviado desde hace al menos 72 horas. Un correo pendiente o fallido impide borrar. Desactivar la regla también impide iniciar el aviso automático necesario. La protección contra duplicados se comparte con «Enviar ahora»; un aviso manual reciente puede servir como aviso previo, pero no adelanta la fecha.

Solo se procesan ciclos registrados: no se inventan fechas para cuentas desactivadas antiguas. La reactivación cancela el ciclo, el superadministrador queda protegido y la limpieza de membresías, sesiones y usuario es transaccional. Una relación que impida borrar revierte toda la operación. Esta implementación corresponde al esquema estándar; aplicaciones con otros repositorios deben integrar su propia limpieza. Sin Campañas, Mi cuenta conserva la desactivación sin prometer eliminación automática.

## Variables del contenido

Título y mensaje admiten las variables originales `{{site_url}}`, `{{dashboard_url}}`, `{{notifications_url}}`, `{{user_id}}`, `{{user_name}}`, `{{user_email}}`, `{{user_role}}` y `{{user_status}}`. Los botones del formulario las insertan en la posición del cursor. Se sustituyen por destinatario antes de encolar ambos canales, incluido el asunto del correo. Los tokens desconocidos se conservan.

Las URLs se construyen en el backend. El esquema estándar no incluye un campo de nombre; `user_name` utiliza la parte anterior a `@` del correo. Los adaptadores de cada aplicación pueden proporcionar su nombre real y variables adicionales.

## Transportes

Los canales actuales son `inbox` y `email`. WhatsApp, Telegram, push u otros addons podrán participar sin modificar este módulo. Cada destinatario conserva un valor propio por canal, de modo que el identificador del inbox no se confunde con una dirección de correo.

## Prevención de duplicados

La audiencia se normaliza por campaña, canal y destinatario. La base de datos mantiene además una restricción única sobre esa combinación. Cada destinatario pasa de `pending` a `processing` mediante una reserva condicionada y luego a `queued` o `failed`. Cada trabajo utiliza una clave de deduplicación persistente, por lo que reintentar un lote no crea el mismo envío dos veces.

## Estados

- `scheduled`: espera su fecha o el siguiente lote.
- `running`: está generando trabajos.
- `paused`: no debe continuar hasta reanudarse.
- `completed`: todos los destinatarios fueron procesados.
- `failed`: ningún destinatario del lote pudo encolarse.
- `cancelled`: no generará nuevos trabajos.

Cancelar una campaña no elimina trabajos que ya estén en `notification_queue`.

## Permisos

El listado usa la plantilla `admin` y el título común `.pagetitle` de `admin.css`, sin otro contenedor ni estilos propios para los títulos. «Nueva campaña» navega a `admin/notifications/campaigns/new`, una vista independiente del listado. El formulario original vive en `resources/modules/notification-campaigns/application/app/views/notification-campaigns/form.php`; se personaliza en `app/views/notification-campaigns/form.php`. Sus metadatos y los del listado cargan los recursos del módulo explícitamente. El filtro de estado se aplica automáticamente y la paginación aparece únicamente cuando existen varias páginas de resultados.

La sección Campañas del menú incluye el listado y Nueva campaña. El formulario usa un único título; el controlador guarda ese mismo valor como nombre interno para conservar el esquema. Flatpickr proporciona fecha y hora.

La edición se abre en `admin/notifications/campaigns/edit?id=ID` con el mismo formulario que la creación. Antes de procesar destinatarios permite cambiar contenido, audiencia, canales, fecha, frecuencia, importancia, enlace y caducidad. La actualización de destinatarios, definición y tareas es transaccional; un fallo de programación revierte los cambios. La audiencia se conserva en `notification_campaigns.audience_json`; las campañas anteriores recuperan sus criterios desde la planificación o los destinatarios guardados. El backend comprueba ámbito y estado también al guardar. Vaciar la fecha de una campaña pendiente no pausada guarda y procesa el envío inmediato; una campaña pausada permanece pausada.

En una campaña recurrente ya procesada, los cambios se aplican a sus próximos envíos. La fecha del formulario es la próxima ejecución, no la primera fecha histórica. No se reescriben destinatarios ni trabajos encolados de ocurrencias anteriores. El formulario también permite vista previa y prueba en edición. El endpoint `update` recibe ahora los mismos campos de entrega que `create`, además de `campaign_id`; el JavaScript publicado los envía todos. `updateCampaignContent` se mantiene para integraciones que solo cambian contenido.

«Reciclar campaña» abre `admin/notifications/campaigns/new?source=ID` con título, mensaje, canales, enlace, importancia y caducidad precargados. Guardar crea una campaña nueva; la original conserva su contenido, estado e historial. La nueva audiencia debe seleccionarse expresamente y la fecha anterior no se reutiliza.

## Programación, vista previa y prueba

El formulario sitúa la selección manual antes del mensaje. El calendario utiliza las variables comunes, incluido el tema oscuro. Las acciones quedan a la derecha, con Cancelar antes del botón principal.

Contenido y entrega ocupan bloques separados. Fecha y frecuencia comparten una fila; Canales es el último campo de entrega, antes de los botones auxiliares y las acciones finales. Vista previa y prueba usan botones de contorno primario, distintos de Cancelar. En creación, el botón principal dice «Enviar ahora» sin fecha y «Programar campaña» cuando se selecciona una fecha; en edición dice «Guardar cambios». Los subtítulos utilizan el mismo `span.d-block` bajo el título que el listado de Campañas. El historial de automáticas se abre desde la barra lateral, sin botón adicional en la cabecera del listado de reglas.

La repetición admite una vez, diaria o semanal. La primera fecha se convierte desde la zona del navegador a UTC; las siguientes suman uno o siete días en UTC. Un cambio de horario de verano puede desplazar la hora local. Cada ocurrencia vuelve a consultar la audiencia activa y revalida los destinatarios antes de encolar. Conserva una ocurrencia única por campaña y fecha; los reintentos no duplican trabajos. Tras una interrupción, procesa una ocurrencia pendiente y omite las fechas intermedias vencidas, sin enviar toda la acumulación. Si no hay destinatarios, avanza al siguiente período.

Las ocurrencias se conservan en la base de datos sin llenar el listado principal. La campaña recurrente admite edición de contenido para los próximos envíos, pausa, reanudación y cancelación. Estas acciones también gobiernan sus ocurrencias; no retiran trabajos ya encolados. `completed` significa que terminó de preparar un envío, no que su recurrencia haya terminado ni que el transporte lo haya entregado.

«Ver destinatarios» muestra el total de usuarios elegibles y una muestra de hasta diez. «Enviar prueba a mi cuenta» ignora la audiencia seleccionada y encola únicamente para el usuario conectado en los canales elegidos, con asunto prefijado por `[Prueba]`; no crea una campaña.

Enlace de acción, importancia (`info`, `warning`, `danger`) y caducidad en días se conservan en el contenido y en la cola. Cero significa sin caducidad. El enlace admite HTTP(S), rutas relativas a la aplicación y las variables URL originales; el backend lo construye antes de encolar. El correo incluye el enlace cuando existe y el inbox utiliza su acción. La caducidad se calcula por envío, no desde la creación de la campaña.

## Cola del inbox y campana

`InboxQueueProcessor` consume exclusivamente trabajos `inbox` y los convierte en `user_notifications`. Una transacción agrupa reserva, creación y marcado del trabajo, evitando duplicados por reintentos. El módulo Notificaciones publica el registro cron para procesarlos cada minuto. Sus consultas de inbox, historial y heartbeat también drenan la cola estándar antes de leer; abrir la campana refresca el contenido y contador. Los transportes personalizados inyectados no se sustituyen por este procesamiento estándar.

Los envíos inmediatos, las pruebas y la acción manual de procesar una campaña lanzan el worker de correo mediante Async cuando está instalado `notifications-email`. SMTP nunca se ejecuta en la petición web. `meta.email_worker` informa `started` o `failed`, no confirma entrega. Un fallo de lanzamiento conserva los trabajos para el siguiente procesamiento por cron.

Para reintentos, campañas programadas, recurrencias y revisiones de avisos automáticos debe ejecutarse periódicamente `php bin/gframe-cron.php 50` en el proyecto. Instalar o registrar las tareas no pone en marcha un proceso del servidor.

- `notifications.campaigns.view`: consultar campañas.
- `notifications.campaigns.manage`: crear, procesar, pausar, reanudar y cancelar.

## Contratos

`CampaignRepository` utiliza `findCampaign($campaignID, $tenantID)` y `paginateCampaigns($page, $perPage, $tenantID, $status)`. Sustituyen los antiguos `find` y `paginate`, que colisionaban con los métodos heredados del ORM: `find` es estático y `paginate` tiene una firma distinta. Los adaptadores personalizados deben renombrar ambos métodos. El controlador y el servicio usan los nuevos nombres; la paginación interna conserva `ORM::paginate` y las rutas y respuestas HTTP no cambian.

Las respuestas usan `status`, `code`, `message`, `data`, `meta` y `html`. Los errores internos se registran; no se devuelven excepciones ni detalles de base de datos a la interfaz.

Los campos nuevos de contenido son aditivos: `recurrence`, `parent_id`, `importance`, `action_url` y `expires_after_days`. Los repositorios personalizados deben almacenarlos si usan estas funciones. El controlador estándar registra la planificación mediante `CampaignRecurrenceModel::register`; una integración que llame directamente a `CampaignService::create` debe registrar asimismo su planificación y audiencia. La programación utiliza UTC; los timestamps del inbox y de entrega de su cola conservan la zona de la aplicación utilizada por sus modelos, sin mezclar ambas interpretaciones.
