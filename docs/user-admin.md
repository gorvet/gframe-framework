# Administración de usuarios

El módulo `user-admin` permite consultar usuarios, filtrar la lista, verificar cuentas, suspender/restablecer el acceso, eliminar cuentas y asignar roles. No crea cuentas ni cambia contraseñas administrativamente. Las acciones se agrupan en el modal «Administrar», dentro de la plantilla administrativa común.

## Instalación

```powershell
php bin/modules.php publish-project user-admin C:\ruta\del\proyecto
```

El módulo requiere el esquema `auth` y publica el controlador, las rutas, las vistas y el JavaScript. También instala sus dependencias `admin-panel`, `alerts` y `frontend-core`. La pantalla usa la plantilla administrativa y aporta su enlace dentro de Administración cuando el usuario tiene `users.view`. Mi cuenta es un módulo independiente.

La vista utiliza título administrativo, filtros con etiquetas visibles y listado en una tarjeta separada, con el padding normal de `card-body`. Los roles se obtienen del contrato de GFrame. Las acciones se agrupan en un modal, los estados tienen etiquetas en español y la cuenta propia y el superadministrador no muestran acciones de modificación. La búsqueda se realiza por correo.

Los filtros buscan por AJAX automáticamente: 300 ms después de escribir y al cambiar un selector. No hay botón Filtrar; el botón de limpiar usa gicon-close y solo aparece cuando hay filtros activos. La ruta de lista devuelve el parcial HTML y los metadatos; solo se sustituye userListMount. La URL conserva filtros y página, y se cancelan las peticiones anteriores para evitar respuestas fuera de orden. Sin JavaScript, el formulario conserva el envío GET con Enter.

## Permisos

Las rutas utilizan dos permisos:

- `users.view`: abrir la pantalla, buscar, filtrar y paginar;
- `users.manage`: cambiar estados y asignar roles.

El superadministrador pasa ambos permisos automáticamente. Para habilitar administradores subordinados, cree estos permisos y asígnelos a los roles correspondientes mediante `RolePermissionService`.

Un rol que solo tenga `users.view` verá los controles de modificación desactivados. El endpoint de actualización siempre exige `users.manage`.

## Rutas

| Método | Ruta | Permiso |
| --- | --- | --- |
| `GET` | `/admin/users` | `users.view` |
| `POST` | `/ajax/admin/users/list` | `users.view` |
| `POST` | `/ajax/admin/users/update` | `users.manage` |

Las rutas AJAX conservan la protección CSRF automática.

## Operaciones incluidas

La lista admite búsqueda por correo, filtro por rol, filtro por estado y paginación. La actualización acepta únicamente estas operaciones:

```text
operation=role
operation=verify
operation=suspend
operation=restore
operation=delete
```

El servicio comprueba que el usuario y el rol existan antes de escribir. También impide modificar al superadministrador y que un administrador cambie su propia cuenta desde esta pantalla.

`verify` solo acepta cuentas `unverify`; `suspend` solo acepta cuentas `verify`; `restore` solo acepta cuentas `suspended`. Así, suspender/restablecer no verifica implícitamente un correo pendiente. `disabled` se reserva a la desactivación voluntaria en Mi cuenta y no se ofrece como operación administrativa, ni se restablece desde este panel. No existe una acción de desverificar.

La eliminación requiere confirmación visible y `confirmed=1` en la petición. El repositorio predeterminado borra la cuenta, sus membresías y sus sesiones dentro de una transacción; una relación externa que impida borrar revierte la operación completa. No borra contenidos, archivos ni entidades propios de una aplicación. Esas reglas pertenecen a su repositorio. Los repositorios personalizados implementan el contrato adicional `UserModerationRepository` (`setAccountStatus()` y `deleteAccount()`) para habilitar las nuevas operaciones; el contrato previo no cambia. El antiguo método `setActive()` se conserva como compatibilidad, pero el controlador administrativo ya no acepta `operation=status`.

Un administrador subordinado no puede activar, desactivar, ascender ni degradar a otro rol con `admin.access`. La administración de esos roles queda reservada al superadministrador.

## Contratos

Las respuestas utilizan `status`, `code`, `message`, `data` y `meta`. La carga inicial entrega:

```php
[
    'status' => 'success',
    'code' => 'users_loaded',
    'message' => 'Usuarios cargados correctamente.',
    'data' => [
        'users' => $paginatedResponse,
        'roles' => $assignableRoles,
        'can_manage' => true,
    ],
]
```

Las excepciones se registran en el servidor y se transforman en códigos estables. El controlador aporta mensajes aptos para la interfaz y el JavaScript los presenta mediante `alertToast`.

## Adaptar el almacenamiento

Declare `app/controllers/user-admin/UserAdminController.php` con namespace `App\Controllers\UserAdmin`, extendiendo `GFrame\Modules\UserAdmin\Controllers\UserAdminController`. Construya su servicio en ese controlador y entréguelo a `parent::__construct(...)`. Las vistas propias van en `app/views/user-admin`; la principal se llama `user-adminIndex.php`. Consulte [estructura y migración](modulos-runtime.md).

Las aplicaciones con otro esquema implementan:

- `GFrame\Auth\Contracts\UserAdministrationRepository` para consultar y modificar usuarios;
- `GFrame\Auth\Contracts\RoleAdministrationRepository` para resolver roles y permisos.

Después se entregan los adaptadores al servicio:

```php
$service = new UserAdministrationService(
    new ApplicationUserAdministrationRepository(),
    new ApplicationRoleAdministrationRepository()
);
```

El controlador original admite inyección de `UserAdministrationService` y `RoleModel` para facilitar esta sustitución y las pruebas.

## Extensiones de la aplicación

El modelo estándar desactiva mediante `updateAuthUser()`, que rota el token y su fecha en la misma escritura que el estado. Así invalida los enlaces de correo anteriores; reactivar no los recupera. Un repositorio propio debe mantener esa garantía y comunicar escrituras fallidas al servicio.

Al desactivar, `UserAdministrationService` revoca todas las sesiones registradas del usuario y bloquea el alta de otras nuevas. Al reactivar, habilita de nuevo el registro; las sesiones anteriores no reaparecen. No se consulta la base de datos desde cada heartbeat. Si una aplicación añade eliminación de usuarios, debe llamar igualmente a `ActiveSessionRegistry::revokeUser()` dentro de ese flujo.

Asignar otro rol revoca las sesiones sin bloquear la cuenta, de modo que el siguiente acceso cargue la identidad vigente. Los cambios meramente descriptivos no deben cerrar sesiones.

Áreas, perfiles, membresías, verificación de identidad, bots y métricas pertenecen a cada proyecto. Pueden añadirse mediante un repositorio propio, filtros adicionales y vistas personalizadas sin cambiar el núcleo.

La creación manual de cuentas y el cambio administrativo de contraseñas no forman parte del módulo actual. «Añadir usuarios desde el admin» queda registrado como mejora no urgente, sin implementar, en [Mejoras pendientes](mejoras-pendientes.md). Si una aplicación los implementa, debe exigir permisos separados, registrar la operación y activar `force_password_change` cuando entregue una contraseña temporal.
