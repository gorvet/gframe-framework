# Cuenta y seguridad

El módulo `self-account` permite que un usuario autenticado consulte sus datos básicos de acceso, cambie su contraseña y desactive su propia cuenta.

## Instalación

Selecciona `self-account` en la instalación o añádelo siguiendo [Instalación de módulos](modulos-opcionales.md#añadir-módulos-a-un-proyecto-instalado). El actualizador recibe la lista completa de módulos deseados, no solo el nuevo.

La publicación resuelve `admin-panel`, `auth-ui`, `alerts` y `frontend-core`, publica rutas y CSS/JS y crea carpetas vacías `app/controllers/self-account` y `app/views/self-account` para personalizar. Solo se preparan las capas presentes en el módulo. El controlador y las vistas originales permanecen en el módulo. También requiere el esquema `auth`. `/account` utiliza la plantilla administrativa, sin una plantilla independiente. Conserva el middleware `auth`, sin exigir un rol administrativo, para permitir el cambio obligatorio de contraseña y la cuenta propia de cualquier usuario autenticado.

La pantalla contiene únicamente operaciones sobre la cuenta conectada. La gestión de otras cuentas pertenece a [Gestión de usuarios](user-admin.md).

## Rutas

| Método | Ruta | Uso |
| --- | --- | --- |
| `GET` | `/account` | Mostrar la cuenta conectada |
| `POST` | `/ajax/account/password` | Cambiar la contraseña |
| `POST` | `/ajax/account/deactivate` | Desactivar la cuenta |

Todas las rutas utilizan el middleware `auth`. Las mutaciones AJAX reciben los tokens CSRF generados al iniciar sesión.

| Acción | Campos POST |
| --- | --- |
| Cambiar contraseña | `current_password`, `new_password`, `new_password_confirmation` |
| Desactivar cuenta | `password` |

El ID se obtiene de la identidad de sesión, no de un campo del formulario. Una cuenta común puede acceder sin `admin.access`; el superadministrador puede cambiar su contraseña, pero la política predeterminada impide su desactivación. La pantalla no ofrece borrado inmediato de la cuenta.

## Contratos de respuesta

El controlador devuelve `status`, `code` y `message`. La carga de la pantalla incluye los datos en `data.account`.

```php
[
    'status' => 'success',
    'code' => 'account_loaded',
    'message' => 'Cuenta cargada correctamente.',
    'data' => ['account' => $account],
]
```

Los errores técnicos se registran en el servidor y se convierten en códigos estables. El JavaScript decide cómo mostrarlos mediante `alertToast` o `swalAlert`.

### Servicio y controlador

`SelfAccountService::profile()` devuelve la cuenta directamente en `data`; el controlador la envuelve en `data.account` para la vista. El servicio elimina `password` y `token` del perfil público, pero necesita el hash de contraseña cuando consulta el repositorio para verificar una operación sensible.

```php
<?php
use GFrame\Auth\SelfAccountService;

$userID = (int)($_SESSION['auth']['id'] ?? 0);
$result = (new SelfAccountService())->profile($userID);
if (($result['status'] ?? '') !== 'success') {
    return $result;
}
return ['status' => 'success', 'data' => ['account' => $result['data']]];
```

`changePassword()` exige la contraseña actual, la nueva y su confirmación. La política predeterminada acepta entre 8 y 72 bytes UTF-8; una discrepancia devuelve `password_mismatch` y una contraseña actual incorrecta devuelve `invalid_current_password`. No toma el hash desde el navegador. El frontend muestra el resultado, pero la aceptación se decide en el backend.

## Cambio obligatorio de contraseña

Cuando el acceso devuelve `must_change_password`, `auth-ui` conserva esa condición en la sesión y redirige a `auth.password_change_redirect`. La pantalla muestra el aviso correspondiente. Después de un cambio correcto, `UserModel` actualiza `password_changed_at`, desactiva `force_password_change`, renueva el token y el controlador limpia la condición de la sesión.

## Extender el repositorio

Una aplicación con un esquema propio puede implementar `GFrame\Auth\Contracts\SelfAccountRepository`:

```php
use GFrame\Auth\Contracts\SelfAccountRepository;

final class ApplicationAccountRepository implements SelfAccountRepository
{
    public function findAccountByID(int $userID): ?array
    {
        // Consulta tu almacenamiento y devuelve la cuenta, con su hash interno.
        throw new \LogicException('Implementa la consulta del proyecto.');
    }
    public function updateAccountPassword(int $userID, string $passwordHash): void
    {
        throw new \LogicException('Implementa la actualización del proyecto.');
    }
    public function deactivateAccount(int $userID): void
    {
        throw new \LogicException('Implementa la desactivación del proyecto.');
    }
}
```

El repositorio puede devolver datos adicionales para el perfil y el hash interno para verificar contraseñas. El servicio elimina `password` y `token` antes de responder públicamente; no envíes otros secretos como campos adicionales.

La implementación es un contrato para esquemas propios, no una capa obligatoria alrededor del modelo estándar. Con el esquema de GFrame utiliza `UserModel`. Si implementas otro almacenamiento, conserva el hash interno, el estado, la identificación del rol y las reglas de revocación; no sustituyas las operaciones por métodos vacíos.

## Política de desactivación

Para decidir qué cuentas pueden desactivarse, implemente `GFrame\Auth\Contracts\AccountDeactivationPolicy` y entréguela al servicio:

```php
$service = new SelfAccountService(
    new ApplicationAccountRepository(),
    new ApplicationAccountDeactivationPolicy()
);
```

La implementación predeterminada protege el rol `superadministrator`. Una aplicación puede añadir restricciones, transacciones o limpieza de relaciones. Por ejemplo, una aplicación de bots puede desconectar canales y eliminar recursos dependientes; una aplicación de gestión puede retirar áreas y permisos. Esas operaciones pertenecen al repositorio de la aplicación, no al núcleo.

## Personalización

Mi cuenta usa la estructura runtime: declare una subclase en `app/controllers/self-account/SelfAccountController.php`, con namespace `App\Controllers\SelfAccount`, o una vista propia `app/views/self-account/self-accountIndex.php`. Los originales son el respaldo cuando faltan las personalizaciones. La actualización no reemplaza estos archivos. Consulte [estructura, namespaces y migración](modulos-runtime.md).

Para conectar un repositorio o una política propios, construya `SelfAccountService` en su controlador personalizado y entréguelo a `parent::__construct(...)`. Consulte [herencia desde proyectos](extensibilidad.md#herencia-de-controladores-de-módulos). No se necesita un archivo adicional de fábricas.

El constructor de `SelfAccountController` permite inyectar `SelfAccountService` y `SessionManager`. Las clases y vistas del proyecto pueden personalizarse para añadir nombre, avatar, preferencias o enlaces, manteniendo los datos de perfil fuera del esquema base de autenticación.

Para ampliar los datos conservando el flujo original, crea el controlador del proyecto:

```php
<?php
namespace App\Controllers\SelfAccount;

class SelfAccountController extends \GFrame\Modules\SelfAccount\Controllers\SelfAccountController
{
    public function index(): array
    {
        $response = parent::index();
        if (($response['status'] ?? '') !== 'success') {
            return $response;
        }
        // Sustituye este dato por la consulta al perfil del usuario conectado.
        $response['data']['preferences'] = ['language' => 'es'];
        return $response;
    }
}
```

La vista personalizada `app/views/self-account/self-accountIndex.php` recibe esos valores en `$data`, por ejemplo `$data['preferences']`. Añade acciones nuevas en rutas propias con `auth` y CSRF para sus mutaciones. No permitas actualizar otra cuenta mediante un ID enviado por el formulario.

Los servicios son final; amplía el comportamiento mediante el controlador, los contratos inyectables o la composición de un servicio del proyecto. Una subclase del modelo no se utiliza automáticamente: construye `new SelfAccountService(accounts: $model)` e inyéctalo con `parent::__construct(accounts: $service)`.

El servicio de cuenta desactiva, no borra. Cuando está activo `notification-campaigns`, el controlador integra el ciclo estándar: correo inmediato, fecha de eliminación a 60 días y aviso previo de 72 horas. La presencia del módulo se comprueba con `ModuleRuntime::has('notification-campaigns')`, no por la existencia de vistas copiadas en `app`. Los valores se configuran en `auth.deactivation.retention_days` y `auth.deactivation.warning_hours`; Mi cuenta muestra la política antes de confirmar con la contraseña. Sin el módulo se conserva la desactivación sin eliminación automática. La ejecución y las salvaguardas se describen en [Campañas](notification-campaigns.md#cuentas-desactivadas).

Una desactivación correcta revoca todas las sesiones abiertas de la cuenta, incluidas las de otros navegadores o dispositivos. El controlador cierra además la sesión actual. Esta invalidación se realiza al cambiar el estado, no mediante consultas periódicas desde heartbeat.

El cambio de contraseña también revoca todas las sesiones sin bloquear la cuenta. El siguiente acceso debe realizarse con la contraseña nueva.
