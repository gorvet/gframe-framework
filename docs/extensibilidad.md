# Extensión desde los proyectos

La vía preferida para ampliar módulos MVC es la herencia PHP y la sustitución de vistas en `app`, con respaldo en el módulo. Auth, Mi cuenta, Gestión de usuarios, Campañas y Notificaciones ya utilizan esta estructura; consulte [estructura runtime y namespaces](modulos-runtime.md). Los contratos de transportes, audiencias y persistencia documentados debajo conectan capacidades externas; no son mecanismos de personalización MVC y no deben añadirse hooks para reemplazar la herencia.

Los módulos distribuibles deben separar sus mecanismos comunes de las reglas propias de cada aplicación. Una extensión pertenece al proyecto y no debe requerir editar archivos administrados por Composer o por `gframe:update`.

Un punto de extensión puede ser un registro de tipos, un callback o un contrato existente. Debe documentar cuándo se ejecuta, qué argumentos recibe, qué devuelve, qué validaciones conserva y cómo participa en web y cron. No se añade un sistema global de hooks al core solo por disponer de una analogía con WordPress.

## Puntos disponibles en Campañas y Notificaciones

- Campañas automáticas: herencia de `AutomaticCampaignModel`, con reglas, audiencias, elegibilidad y variables definidas como métodos. Inyecte el mismo modelo en el controlador y en la subclase del cron. Se mantienen editor, envío manual, revisión periódica, cola e historial. Consulte [Campañas](notification-campaigns.md#extender-las-reglas-por-herencia).
- Audiencias de campañas: contrato `CampaignAudienceProvider` y comprobación `CampaignRecipientGuard`, descritos en [Campañas](notification-campaigns.md#audiencias).
- Transportes: `NotificationTransportRegistry` y `NotificationTransport`, descritos en [Notificaciones](notificaciones.md#transportes).
- Persistencia del inbox: `NotificationRepository`, descrito en [Notificaciones](notificaciones.md#extensión).

Las extensiones deben conservar aislamiento por usuario y tenant, estabilidad de códigos, protección contra duplicados y verificación de enlaces. Los módulos que todavía tengan reglas de proyecto incrustadas deben revisarse al trabajar en ellos; esta documentación no significa que todos los módulos ya sean extensibles ni que exista un bus global de eventos.

## Herencia de controladores de módulos

Los originales permanecen en los módulos. Declare un controlador propio en la carpeta correspondiente de `app/controllers`, con su namespace de proyecto, y extienda el original:

| Módulo | Namespace del proyecto | Controlador original |
| --- | --- | --- |
| auth-ui | `App\Controllers\AuthUi` | `GFrame\Modules\AuthUi\Controllers\AuthController` |
| self-account | `App\Controllers\SelfAccount` | `GFrame\Modules\SelfAccount\Controllers\SelfAccountController` |
| user-admin | `App\Controllers\UserAdmin` | `GFrame\Modules\UserAdmin\Controllers\UserAdminController` |
| notification-campaigns | `App\Controllers\NotificationCampaigns` | `GFrame\Modules\NotificationCampaigns\Controllers\CampaignController` |
| notifications | `App\Controllers\Notifications` | `GFrame\Modules\Notifications\Controllers\NotificationController` |

Para usar un modelo o servicio propio, constrúyalo en el controlador personalizado e inyéctelo mediante `parent::__construct(...)`. Los constructores conservan sus dependencias opcionales; crear una subclase no sustituye automáticamente una instancia de la clase original. Las vistas personalizadas siguen la misma ruta relativa en `app/views/<módulo>`.

Ejemplo en `app/controllers/auth-ui/AuthController.php`:

```php
<?php
namespace App\Controllers\AuthUi;

class AuthController extends \GFrame\Modules\AuthUi\Controllers\AuthController
{
    public function __construct()
    {
        parent::__construct(new \App\Models\AuthUi\ProjectAuthModel());
    }
}
```

El modelo del ejemplo pertenece al proyecto y extiende `GFrame\Auth\AuthModel`. No se cambian URLs, campos de formulario, permisos ni códigos de respuesta. Conserve contraseñas, tokens, sesiones y protección de cuentas. Auth mantiene controlador → modelo → ORM y no crea tenants.

Se retiró el mecanismo de fábricas `config/auth/extensions.php`. Si un proyecto ya lo tiene, sus entradas deben trasladarse a los constructores personalizados: ya no se consultan. La actualización no borra ese archivo ni las personalizaciones anteriores. Un esquema de cuentas propio necesita además una integración compatible con el ciclo de eliminación; la herencia no reemplaza automáticamente la limpieza estándar de Campañas.

Campañas ya utiliza clases y vistas propias: su registro de callbacks fue retirado. `alerts` publica los recursos JS/CSS de toast, confirmaciones y estados de carga; no tiene controladores, modelos ni vistas PHP y no requiere conversión al runtime MVC. No debe confundirse con `notifications`, que sí es un módulo MVC ya convertido. Los contratos de transportes y persistencia siguen siendo capacidades externas intercambiables, no hooks de presentación.
