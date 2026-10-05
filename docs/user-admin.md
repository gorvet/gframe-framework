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


## Contrato del servicio

`UserAdministrationService` concentra autorización, filtros, cambios de rol y moderación. El controlador del módulo no debe saltarse este servicio para ejecutar escrituras directamente sobre el modelo.

Sus operaciones públicas principales son:

| Método | Uso |
| --- | --- |
| `paginate($actorID, $page, $perPage, $search, $role, $status)` | Lista usuarios visibles para el actor |
| `assignableRoles($actorID)` | Devuelve los roles que pueden ofrecerse en la interfaz |
| `capabilities($actorID)` | Informa si el actor puede ver y administrar |
| `assignRole($actorID, $userID, $roleID)` | Cambia el rol y revoca las sesiones del usuario |
| `moderate($actorID, $userID, $operation)` | Verifica, suspende, restaura o elimina |
| `setActive($actorID, $userID, $active)` | Activa o desactiva desde integraciones que usen este contrato |

`users.manage` permite las escrituras. Para lectura basta `users.view` o `users.manage`. El superadministrador supera ambas comprobaciones mediante su rol del sistema.

### Filtros y paginación

El servicio normaliza los filtros antes de consultar el repositorio:

- `page` nunca baja de 1;
- `perPage` queda entre 1 y 100;
- la búsqueda se recorta a 120 caracteres;
- el filtro de rol acepta slugs normalizados;
- el estado solo admite `verify`, `unverify`, `disabled` o `suspended`.

El controlador estándar utiliza 20 elementos por página. Un valor de filtro inválido se normaliza a vacío y no se transmite como un estado o rol arbitrario al repositorio.

### Transiciones protegidas

La moderación no es un cambio libre de estado:

| Operación | Estado requerido | Resultado |
| --- | --- | --- |
| `verify` | `unverify` | `verify` |
| `suspend` | `verify` | `suspended` |
| `restore` | `suspended` | `verify` |
| `delete` | cuenta administrable | eliminación |

Una transición incompatible devuelve `invalid_status_transition`. El servicio también bloquea la propia cuenta del actor y la cuenta del superadministrador. Un actor que no sea superadministrador tampoco puede administrar una cuenta cuyo rol tenga `admin.access`, ni asignar un rol administrativo.

La operación de moderación requiere que el repositorio implemente `UserModerationRepository`; de lo contrario devuelve `moderation_not_supported`.

### Sesiones y revocación

Los cambios administrativos afectan las sesiones activas:

- suspender o desactivar revoca sesiones y bloquea al usuario en el registro de sesiones;
- restaurar o activar vuelve a permitir la cuenta, pero no recupera sesiones antiguas;
- cambiar el rol revoca sesiones para que la próxima autenticación cargue la nueva autorización;
- eliminar revoca y bloquea las sesiones después de borrar la cuenta.

Un adaptador propio que ignore estas garantías puede dejar sesiones con permisos anteriores aunque el registro de usuario ya haya cambiado.

## Códigos de respuesta

Los códigos funcionales más importantes son:

| Código | Significado |
| --- | --- |
| `users_loaded` | Listado cargado |
| `roles_loaded` | Roles cargados |
| `role_assigned` | Rol actualizado |
| `user_verified` | Cuenta verificada |
| `user_suspended` | Cuenta suspendida |
| `user_restored` | Cuenta restaurada |
| `user_deleted` | Cuenta eliminada |
| `forbidden` | El actor no tiene autorización |
| `self_protection` | Intento de modificar la propia cuenta |
| `protected_user` | Cuenta protegida por jerarquía |
| `invalid_role_assignment` | Rol inexistente o no asignable |
| `invalid_status_transition` | Operación incompatible con el estado actual |
| `moderation_not_supported` | El repositorio no implementa moderación |

Los mensajes públicos se añaden en el controlador. Las integraciones deben reaccionar al código estable, no comparar el texto del mensaje.

## Recorrido del listado AJAX

La pantalla inicial llama al mismo servicio que el endpoint AJAX. El recorrido es:

```text
/admin/users
  -> UserAdminController::index()
  -> UserAdministrationService::paginate()
  -> repositorio
  -> vista completa

filtros / búsqueda / página
  -> /ajax/admin/users/list
  -> UserAdminController::list()
  -> mismo servicio
  -> _userList.php
  -> JSON + html + meta
```

Esto evita mantener dos consultas diferentes para la carga inicial y las actualizaciones del listado. Una personalización de repositorio debe conservar el mismo contrato de paginación para que ambos recorridos sigan funcionando.
