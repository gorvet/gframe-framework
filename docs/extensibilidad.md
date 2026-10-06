# Extensión desde los proyectos

Amplía los módulos MVC mediante clases del proyecto y vistas en `app`. El framework resuelve primero la personalización y después el archivo del módulo. Consulta [Módulos del framework](modulos-runtime.md) para sus carpetas, namespaces y declaración de rutas.

Los módulos distribuibles deben separar sus mecanismos comunes de las reglas propias de cada aplicación. Una extensión pertenece al proyecto y no debe requerir editar archivos administrados por Composer o por `gframe:update`.

El mecanismo depende de lo que quieras modificar:

| Necesidad | Mecanismo |
| --- | --- |
| Presentación de una pantalla | Vista, parte o template del proyecto |
| Acción de un módulo MVC | Controlador heredado en `app/controllers/<módulo>/` |
| Consulta o regla de negocio | Modelo o servicio propio, construido e inyectado por el controlador |
| Nuevo canal de notificación o almacenamiento | Implementación del contrato que ofrece el módulo |
| Nueva regla de campaña automática | Modelo heredado e integración tanto con el controlador como con cron |

Crear una clase heredada no reemplaza las instancias construidas con `new` en otras clases. Cada punto de entrada que deba utilizarla necesita recibir la dependencia propia.

## Puntos disponibles en Campañas y Notificaciones

- Campañas automáticas: herencia de `AutomaticCampaignModel`, con reglas, audiencias, elegibilidad y variables definidas como métodos. Inyecte el mismo modelo en el controlador y en la subclase del cron. Se mantienen editor, envío manual, revisión periódica, cola e historial. Consulte [Campañas](notification-campaigns.md#extender-las-reglas-por-herencia).
- Audiencias de campañas: contrato `CampaignAudienceProvider` y comprobación `CampaignRecipientGuard`, descritos en [Campañas](notification-campaigns.md#audiencias).
- Transportes: `NotificationTransportRegistry` y `NotificationTransport`, descritos en [Notificaciones](notificaciones.md#transportes).
- Persistencia del inbox: `NotificationRepository`, descrito en [Notificaciones](notificaciones.md#extensión).

Las extensiones deben conservar aislamiento por usuario y tenant, estabilidad de códigos, protección contra duplicados y verificación de enlaces. Usa el contrato documentado por cada módulo para conocer sus argumentos, resultados y puntos de integración.

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

En `app/models/auth-ui/ProjectAuthModel.php`, una subclase mínima conserva el comportamiento original:

```php
<?php
namespace App\Models\AuthUi;

class ProjectAuthModel extends \GFrame\Auth\AuthModel
{
}
```

Añade únicamente los métodos o cambios que necesita el proyecto, respetando las firmas y las comprobaciones del modelo original. No es necesario crear esta clase si no vas a ampliar el modelo. La ruta de autenticación debe conservar la procedencia `auth-ui` para que sus vistas sigan teniendo respaldo en el módulo.

## Vistas y actualizaciones

La sustitución de vistas y partes se describe en [Módulos del framework](modulos-runtime.md#namespaces-y-herencia-php). Copia el original solo cuando lo necesites y modifica la copia en `app/views/<módulo>/`. Los archivos del paquete permanecen administrados por Composer.

Actualizar el paquete no mezcla automáticamente las mejoras de una vista original con una vista personalizada. Compara ambas y decide qué incorporar. Si retiras la personalización, el framework vuelve a utilizar la vista del módulo. Comprueba también los constructores y las firmas de métodos que heredes al actualizar.

Si amplías el esquema de cuentas, integra también la eliminación de los datos asociados; heredar un modelo no modifica la limpieza de otros módulos. Consulta las guías de [Cuenta y seguridad](self-account.md) y [Campañas](notification-campaigns.md) para sus puntos de integración.


## Límites y migraciones de mecanismos antiguos

GFrame no incorpora un bus global de hooks como mecanismo universal de personalización. Un punto de extensión debe estar definido por una API concreta: herencia, constructor inyectable, registro de transportes, contrato de persistencia, proveedor de audiencia u otro mecanismo documentado. Cada punto debe especificar cuándo se ejecuta, qué argumentos recibe, qué devuelve y qué validaciones debe conservar.

Los mecanismos antiguos basados en `config/auth/extensions.php` dejaron de formar parte del contrato de Auth. Si un proyecto conserva ese archivo, traslade sus integraciones a controladores, modelos o servicios personalizados y a sus constructores; la actualización no borra automáticamente el archivo antiguo.

Campañas siguió la misma dirección: el registro histórico de callbacks y `config/notifications/automatic-campaigns.php` ya no se cargan como mecanismo de extensión. Las reglas propias deben vivir en métodos del modelo heredado, y el mismo modelo debe conectarse al controlador y al cron cuando corresponda. Los archivos antiguos pueden conservarse durante una migración, pero no deben considerarse activos por su mera presencia.

No todos los módulos necesitan convertirse a MVC. `alerts`, por ejemplo, publica recursos de interfaz y no requiere controladores, modelos o vistas PHP. No confunda un componente visual con `notifications`, que sí expone lógica y contratos de dominio.

Una extensión correcta debe conservar aislamiento por usuario o tenant, estabilidad de códigos de respuesta, autorización, idempotencia o deduplicación cuando aplique y validación de enlaces o destinatarios. Heredar una clase no transfiere automáticamente estas garantías a código nuevo.
