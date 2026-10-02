# Módulos con originales y personalizaciones separadas

## Estructura e instalación

Un módulo runtime conserva sus originales en una estructura equivalente a `app`. El proyecto contiene solamente las personalizaciones activas. El instalador crea carpetas vacías únicamente para las capas que contienen archivos originales: controladores, modelos, servicios o vistas. Error-pages solo crea su carpeta de vistas; no copia las clases ni las vistas originales. Las rutas se publican en `config/routes` y los CSS/JS siguen publicándose en `public` según el manifiesto.

```text
Proyecto/
├── app/
│   ├── controllers/self-account/
│   └── views/self-account/
└── packages/gframe/framework/resources/modules/self-account/
    └── application/app/
        ├── controllers/self-account/SelfAccountController.php
        └── views/self-account/
            ├── self-accountIndex.php
            └── self-account.group.meta.php
```

`packages` es la carpeta Composer del proyecto, no otra capa de ejecución. El catálogo proporciona la raíz física del módulo; el runtime activa solamente los módulos instalados del registro `storage/gframe-installed.json`.

`ModuleRuntime::isInstalled('notifications-email')` consulta ese registro cargado al arrancar e incluye módulos sin MVC. `ModuleRuntime::has('self-account')` comprueba específicamente un módulo runtime activo. No mantenga una lista independiente en `config/modules.php`. Los archivos antiguos de ese nombre no se cargan ni se borran automáticamente. Las carpetas de personalización antiguas tampoco se eliminan: pueden contener trabajo propio; una capa nueva se puede crear manualmente cuando el proyecto la necesite.

## Manifiesto y rutas

```php
'runtime' => [
    'root' => 'application/app',
    'namespace' => 'GFrame\\Modules\\SelfAccount',
    // Solo para módulos que aporten templates compartidos:
    'templates' => ['miTemplate'],
],
```

La raíz debe existir dentro del módulo. No declare sus controladores, modelos, servicios ni vistas como destinos de publicación en `application`: se cargarán desde el original. Declare allí las rutas y los archivos que realmente deban publicarse. Los módulos MVC del catálogo utilizan esta estructura. Las bibliotecas visuales y los módulos que solo publican recursos, configuración, plantillas de correo o entradas CLI no necesitan un runtime MVC.

```php
Route::get('account', 'self-account/SelfAccountController@index')
    ->module('self-account')
    ->template('admin')
    ->middleware(['auth'])
    ->registerFinal();
```

La URL sigue siendo `account`; la carpeta funcional es `self-account`, como el módulo. RouteBuilder guarda la procedencia en `sourceModule`; el parámetro histórico `module` del Router mantiene su uso para permisos. Si se omite `module()`, se infiere desde la última carpeta del controlador únicamente cuando coincide con un módulo runtime instalado. No se adivinan equivalencias entre nombres. Use la declaración explícita cuando la ruta relativa sea diferente del nombre del módulo.

La inferencia de vista y template no cambia: carpeta + acción con inicial mayúscula para la vista, última carpeta para el template. La vista del ejemplo es `self-accountIndex`. `view('otroNombre')` y `template('otroTemplate')` tienen prioridad.

Router y Render buscan el controlador del proyecto primero y el del módulo indicado después. Render aplica la misma prioridad a vista, meta de grupo, meta de vista y parciales del footer. Una vista declarada con otro nombre se busca con ese nombre; no se sustituye por una vista distinta si falta. Sin módulo asociado, no se busca indiscriminadamente en otros módulos. Las rutas y el middleware conservan sus contratos anteriores.

Los templates siguen agrupados en `views/templates`. Primero se consulta el proyecto, luego el módulo de la ruta. Un template compartido puede proceder de otro módulo runtime que declare su nombre en `runtime.templates`; varios proveedores para un mismo nombre producen un error. `admin-panel` aporta `admin` y `error-pages` aporta `error`; sus templates originales permanecen en el paquete.

## Namespaces y herencia PHP

El controlador original usa `GFrame\Modules\SelfAccount\Controllers\SelfAccountController`. La personalización se guarda en `app/controllers/self-account/SelfAccountController.php`:

```php
<?php
namespace App\Controllers\SelfAccount;

class SelfAccountController extends
    \GFrame\Modules\SelfAccount\Controllers\SelfAccountController
{
    public function index(): array
    {
        $response = parent::index();
        if (($response['status'] ?? '') !== 'success') return $response;
        // Cargar aquí datos propios del proyecto.
        return $response;
    }
}
```

Los originales usan `<namespace del manifiesto>\Controllers`, `\Models` o `\Services`. Las clases del proyecto usan `App\Controllers\SelfAccount`, `App\Models\SelfAccount` o `App\Services\SelfAccount`. La carpeta `self-account` se convierte en `SelfAccount` para el namespace. Las subcarpetas posteriores mantienen su estructura de namespace y deben respetar mayúsculas en Linux. El namespace nativo omite la carpeta inicial que coincide con el módulo; si se declara otra ruta relativa, el namespace nativo conserva todas sus carpetas.

El autoloader del módulo carga el original cuando una subclase referencia su namespace. Un modelo original puede extender `ORM`; la subclase conserva esa herencia. No se necesita una interfaz para heredar implementación. Las clases extensibles no deben ser `final`; exponga como `protected` solo los miembros necesarios. Conserve las comprobaciones de seguridad.

Una instancia creada con `new ClaseOriginal()` no cambia automáticamente por existir una subclase. El controlador personalizado debe construir e inyectar su modelo o servicio personalizado. La herencia no equivale a un contenedor de dependencias.

Para personalizar la presentación, cree `app/views/self-account/self-accountIndex.php`. Recibe los mismos datos del controlador. Puede copiar manualmente la original para empezar. Sin esa personalización, se utiliza la original actualizada. Para parciales propios de un módulo, use `ModuleRuntime::file('views', 'self-account/_partial.php', 'self-account')`, compruebe el resultado y después inclúyalo. Un `include` directo a `app` no obtiene respaldo automáticamente.

## Actualización y migración

Admin-panel, Error-pages y Heartbeat también utilizan runtime. El escritorio conserva la URL `admin`, declara `admin-panel/AdminController` y mantiene la vista explícita `adminIndex`. Sus parciales se personalizan bajo `app/views/admin-panel/parts/`; los menús y acciones publicados por otros módulos usan esa misma ubicación. ErrorResponder conserva su contrato y señala `sourceModule=error-pages`, con vistas bajo `app/views/error-pages/`. El parcial `_errorCard.php` usa la misma prioridad proyecto/original. Heartbeat conserva `ajax/heartbeat` y sus middleware, pero declara `heartbeat-client/HeartbeatController`; para añadir canales, herede en `app/controllers/heartbeat-client/HeartbeatController.php` y llame a `parent::__construct()`.

Las ubicaciones anteriores `app/controllers/admin/`, `app/views/admin/`, `app/views/error/` y `app/controllers/system/heartbeat/` no se eliminan automáticamente. Traslade expresamente sus personalizaciones al nuevo nombre del módulo y ajuste los namespaces. Un template personalizado antiguo en `app/views/templates/` sigue teniendo prioridad: revise sus includes antiguos o retire esa personalización si desea usar el original actualizado. Los archivos publicados de rutas y aportaciones de menú sí se actualizan; CSS y JS conservan sus destinos.

La actualización crea las carpetas que falten, pero no administra ni sobrescribe los archivos personalizados de un módulo runtime. La simulación no crea carpetas. Una personalización no recibe automáticamente cambios de su original: el desarrollador decide incorporarlos o retirar su copia.

Auth, Mi cuenta, Gestión de usuarios, Campañas y Notificaciones ya están convertidos. Sus URLs y campos POST no cambian. La ruta del controlador cambia de `account/SelfAccountController` a `self-account/SelfAccountController`; la vista cambia de `account/accountIndex.php` a `self-account/self-accountIndex.php`. Al actualizar se reemplazan las rutas administradas, pero no se borran los archivos anteriores de `app/controllers/account` y `app/views/account`. Traslade las personalizaciones antiguas expresamente, ajustando namespace y nombre de vista. No las copie ni elimine automáticamente.

Auth cambia de `auth/AuthController` a `auth-ui/AuthController`; conserva los nombres explícitos de sus cinco vistas dentro de `app/views/auth-ui`. Gestión de usuarios cambia de `admin/users/UserAdminController` a `user-admin/UserAdminController`, y de `admin/users/usersIndex.php` a `user-admin/user-adminIndex.php`; su parcial pasa a `user-admin/_userList.php`. Las URLs públicas, los permisos y los campos POST permanecen iguales.

El template nativo de Auth queda en `application/app/views/templates/authTemplate.php`; una personalización en `app/views/templates/authTemplate.php` tiene prioridad. El listado inicial y el AJAX de Gestión de usuarios resuelven el mismo parcial, primero personalizado y después nativo. Heartbeat carga el controlador de Auth con esta misma prioridad.

Las fábricas de `config/auth/extensions.php` ya no se consultan. Traslade su construcción de dependencias a los controladores personalizados; no se elimina el archivo del proyecto automáticamente. Campañas también utiliza `notification-campaigns/CampaignController`, sus vistas en `notification-campaigns` y modelos heredables. Su registro de callbacks fue retirado; consulte [la migración de Campañas](notification-campaigns.md#extender-las-reglas-por-herencia). Multimedia también utiliza el runtime. `alerts` es un componente JS/CSS sin MVC PHP: publica sus recursos públicos y no necesita declarar `runtime`.
