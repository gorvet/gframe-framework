# Administración de usuarios

`user-admin` permite listar cuentas, buscar por correo, filtrar por rol o estado y administrar acceso y roles. Se incluye en los perfiles administrados. La cuenta propia se gestiona en [Cuenta y seguridad](self-account.md).

## Acceder a la pantalla

Abre `/admin/users` con el superadministrador inicial. Para permitir acceso a otro rol, declara sus capacidades en `config/Permissions.php`:

```php
return [
    'gestor' => [
        'admin' => ['access' => true],
        'users' => ['view' => true, 'manage' => true],
    ],
];
```

Integra esa entrada con los roles que ya tenga el archivo, ejecuta `composer gframe:update` y asigna el rol al usuario. Consulta [roles y permisos](permisos.md).

| Capacidad | Permite |
| --- | --- |
| `users.view` | Consultar, buscar, filtrar y paginar. |
| `users.manage` | Verificar, suspender, restablecer, eliminar y asignar roles. |

El superadministrador tiene acceso automáticamente. La comprobación del servidor se mantiene aunque un control esté oculto o deshabilitado.

## Operaciones

Selecciona «Administrar» en una fila:

| Operación | Condición |
| --- | --- |
| Asignar rol | El rol existe y el actor tiene autoridad para asignarlo. |
| Verificar | La cuenta está `unverify`. |
| Suspender | La cuenta está `verify`. |
| Restablecer | La cuenta está `suspended`; vuelve a estar verificada. |
| Eliminar | Requiere confirmación y elimina la cuenta estándar y sus relaciones de autenticación. |

No permite modificar al superadministrador ni administrar la propia cuenta desde esta pantalla. Un administrador subordinado tampoco puede modificar cuentas con un rol que tenga `admin.access`.

`disabled` corresponde a la desactivación voluntaria en Cuenta y seguridad; no se restablece desde esta pantalla. Crear cuentas o cambiar contraseñas administrativamente no son funciones incluidas.

### Por qué solo aparece «Usuario registrado»

La instalación base crea `superadministrator` y `registered`. El primero es protegido y no se puede asignar a otra cuenta. Si necesitas editor, gestor u otros roles, decláralos en `config/Permissions.php` y sincroniza con el actualizador. No se crean ni asignan automáticamente por tener un nombre en una vista.

## Rutas y respuestas

| Método | Ruta | Función |
| --- | --- | --- |
| GET | `admin/users` | Pantalla inicial. |
| POST | `ajax/admin/users/list` | Listado filtrado. |
| POST | `ajax/admin/users/update` | Operación administrativa. |

Las rutas AJAX exigen autenticación y CSRF. El listado entrega JSON con datos, metadatos de paginación y el parcial HTML. La interfaz actualiza la lista automáticamente y conserva filtros y página en la URL.

Las operaciones responden con `status`, `code` y `message`, y datos adicionales cuando corresponda. Las excepciones se registran en el servidor; el navegador recibe mensajes públicos.

## Personalizar la vista

Crea `app/views/user-admin/user-adminIndex.php` para sustituir la pantalla, o `app/views/user-admin/_userList.php` para cambiar las filas. La carga inicial y el listado AJAX utilizan el mismo parcial.

Puedes copiar el original desde `packages/gorvet/gframe/resources/modules/user-admin/application/app/views/user-admin/`. Registra los recursos propios en los metadatos de grupo o vista. Consulta [módulos runtime](modulos-runtime.md).

## Personalizar el controlador y los datos

Para conservar el almacenamiento estándar y añadir comportamiento, crea `app/controllers/user-admin/UserAdminController.php`:

```php
<?php
namespace App\Controllers\UserAdmin;

class UserAdminController extends \GFrame\Modules\UserAdmin\Controllers\UserAdminController
{
    public function index(): array
    {
        $response = parent::index();
        if (($response['status'] ?? '') !== 'success') return $response;
        $response['data']['support_email'] = 'soporte@example.com';
        return $response;
    }
}
```

En la vista personalizada, el valor se encuentra en `$data['data']['support_email']`.

Para otro esquema, implementa los contratos `GFrame\Auth\Contracts\UserAdministrationRepository`, `UserModerationRepository` y `RoleAdministrationRepository`. Construye el servicio con esos adaptadores e inyéctalo desde el constructor personalizado:

```php
public function __construct()
{
    parent::__construct(
        new \GFrame\Auth\UserAdministrationService(
            new \App\Models\UserAdmin\ProjectUserRepository(),
            new \App\Models\UserAdmin\ProjectRoleRepository()
        )
    );
}
```

Las clases `ProjectUserRepository` y `ProjectRoleRepository` del ejemplo deben implementarse y registrarse en el autoload del proyecto. El servicio admite además un registro de sesiones alternativo como tercer argumento.

## Garantías del almacenamiento

Los adaptadores propios deben conservar estas garantías:

- Suspender o desactivar invalida tokens de correo y revoca las sesiones. Reactivar no recupera enlaces ni sesiones antiguos.
- Cambiar el rol revoca sesiones para que el siguiente acceso cargue la nueva autorización.
- Borrar la cuenta y sus relaciones de autenticación ocurre en una transacción; un fallo revierte la operación.
- La eliminación no borra archivos, artículos ni entidades del negocio. Su limpieza debe integrarse en el repositorio propio.

Las personalizaciones de `app` no se sobrescriben al actualizar. No modifiques directamente los controladores o vistas del paquete.
