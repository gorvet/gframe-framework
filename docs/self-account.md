# Mi cuenta

El módulo `self-account` permite que un usuario autenticado consulte sus datos básicos de acceso, cambie su contraseña y desactive su propia cuenta.

## Instalación

```powershell
php bin/modules.php publish-project self-account C:\ruta\del\proyecto
```

La publicación resuelve `admin-panel`, `auth-ui`, `alerts` y `frontend-core`, publica rutas y CSS/JS y crea carpetas vacías `app/{controllers,models,services,views}/self-account` para personalizar. El controlador y las vistas originales permanecen en el módulo. También requiere el esquema `auth`. `/account` utiliza la plantilla administrativa, sin una plantilla independiente. Conserva el middleware `auth`, sin exigir un rol administrativo, para permitir el cambio obligatorio de contraseña y la cuenta propia de cualquier usuario autenticado.

«Mi cuenta» contiene únicamente operaciones sobre la cuenta conectada. La gestión de otras cuentas pertenece al módulo independiente `user-admin`, bajo el apartado «Gestión de usuarios» del panel.

## Rutas

| Método | Ruta | Uso |
| --- | --- | --- |
| `GET` | `/account` | Mostrar la cuenta conectada |
| `POST` | `/ajax/account/password` | Cambiar la contraseña |
| `POST` | `/ajax/account/deactivate` | Desactivar la cuenta |

Todas las rutas utilizan el middleware `auth`. Las mutaciones AJAX reciben los tokens CSRF generados al iniciar sesión.

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

## Cambio obligatorio de contraseña

Cuando el acceso devuelve `must_change_password`, `auth-ui` conserva esa condición en la sesión y redirige a `auth.password_change_redirect`. La pantalla muestra el aviso correspondiente. Después de un cambio correcto, `UserModel` actualiza `password_changed_at`, desactiva `force_password_change`, renueva el token y el controlador limpia la condición de la sesión.

## Extender el repositorio

Una aplicación con un esquema propio puede implementar `GFrame\Auth\Contracts\SelfAccountRepository`:

```php
use GFrame\Auth\Contracts\SelfAccountRepository;

final class ApplicationAccountRepository implements SelfAccountRepository
{
    public function findAccountByID(int $userID): ?array {}
    public function updateAccountPassword(int $userID, string $passwordHash): void {}
    public function deactivateAccount(int $userID): void {}
}
```

El repositorio puede devolver datos adicionales para la vista, pero nunca debe exponer `password` ni `token`; el servicio elimina ambos campos antes de responder.

## Política de desactivación

Para decidir qué cuentas pueden desactivarse, implemente `GFrame\Auth\Contracts\AccountDeactivationPolicy` y entréguela al servicio:

```php
$service = new SelfAccountService(
    new ApplicationAccountRepository(),
    new ApplicationAccountDeactivationPolicy()
);
```

La implementación predeterminada protege el rol `superadministrator`. Una aplicación puede añadir restricciones, transacciones o limpieza de relaciones. Por ejemplo, Una aplicación de bots puede desconectar canales y eliminar recursos dependientes; Una aplicación de gestión puede retirar áreas y permisos. Esas operaciones pertenecen al repositorio de la aplicación, no al núcleo.

## Personalización

Mi cuenta usa la estructura runtime: declare una subclase en `app/controllers/self-account/SelfAccountController.php`, con namespace `App\Controllers\SelfAccount`, o una vista propia `app/views/self-account/self-accountIndex.php`. Los originales son el respaldo cuando faltan las personalizaciones. La actualización no reemplaza estos archivos. Consulte [estructura, namespaces y migración](modulos-runtime.md).

Para conectar un repositorio o una política propios, construya `SelfAccountService` en su controlador personalizado y entréguelo a `parent::__construct(...)`. Consulte [herencia desde proyectos](extensibilidad.md#herencia-de-auth-mi-cuenta-y-gestión-de-usuarios). No se necesita un archivo adicional de fábricas.

El constructor de `SelfAccountController` permite inyectar `SelfAccountService` y `SessionManager`. Las clases y vistas del proyecto pueden personalizarse para añadir nombre, avatar, preferencias o enlaces, manteniendo los datos de perfil fuera del esquema base de autenticación.

El servicio de cuenta desactiva, no borra. Cuando está activo `notification-campaigns`, el controlador integra el ciclo estándar: correo inmediato, fecha de eliminación a 60 días y aviso previo de 72 horas. La presencia del módulo se comprueba con `ModuleRuntime::has('notification-campaigns')`, no por la existencia de vistas copiadas en `app`. Los valores se configuran en `auth.deactivation.retention_days` y `auth.deactivation.warning_hours`; Mi cuenta muestra la política antes de confirmar con la contraseña. Sin el módulo se conserva la desactivación sin eliminación automática. La ejecución y las salvaguardas se describen en [Campañas](notification-campaigns.md#cuentas-desactivadas).

Una desactivación correcta revoca todas las sesiones abiertas de la cuenta, incluidas las de otros navegadores o dispositivos. El controlador cierra además la sesión actual. Esta invalidación se realiza al cambiar el estado, no mediante consultas periódicas desde heartbeat.

El cambio de contraseña también revoca todas las sesiones sin bloquear la cuenta. El siguiente acceso debe realizarse con la contraseña nueva.
