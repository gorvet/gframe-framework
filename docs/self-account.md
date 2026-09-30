# Mi cuenta

El módulo `self-account` permite que un usuario autenticado consulte sus datos básicos de acceso, cambie su contraseña y desactive su propia cuenta.

## Instalación

```powershell
php bin/modules.php publish-project self-account C:\ruta\del\proyecto
```

La publicación resuelve `auth-ui`, `alerts` y `frontend-core`, y agrega el controlador, las rutas, la vista, la plantilla, el CSS y el JavaScript. También requiere el esquema `auth`.

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

La implementación predeterminada protege el rol `superadministrator`. Una aplicación puede añadir restricciones, transacciones o limpieza de relaciones. Por ejemplo, Bebots debe desconectar canales y eliminar recursos dependientes; Base Confías puede retirar áreas y permisos. Esas operaciones pertenecen al repositorio de la aplicación, no al núcleo.

## Personalización

El constructor de `SelfAccountController` permite inyectar `SelfAccountService` y `SessionManager`. Los archivos publicados pueden personalizarse para añadir nombre, avatar, preferencias o enlaces, manteniendo los datos de perfil fuera del esquema base de autenticación.

La operación predeterminada desactiva la cuenta; no elimina físicamente registros. Si una aplicación necesita borrado definitivo, debe implementar explícitamente sus reglas de integridad, confirmación y recuperación.

Una desactivación correcta revoca todas las sesiones abiertas de la cuenta, incluidas las de otros navegadores o dispositivos. El controlador cierra además la sesión actual. Esta invalidación se realiza al cambiar el estado, no mediante consultas periódicas desde heartbeat.

El cambio de contraseña también revoca todas las sesiones sin bloquear la cuenta. El siguiente acceso debe realizarse con la contraseña nueva.
