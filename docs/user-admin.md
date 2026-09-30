# Administración de usuarios

El módulo `user-admin` permite consultar usuarios, filtrar la lista, activar o desactivar cuentas y asignar roles. No crea cuentas ni cambia contraseñas administrativamente.

## Instalación

```powershell
php bin/modules.php publish-project user-admin C:\ruta\del\proyecto
```

El módulo requiere el esquema `auth` y publica el controlador, las rutas, las vistas y el JavaScript. También instala sus dependencias `self-account`, `admin-panel`, `alerts` y `frontend-core`. La pantalla usa la plantilla administrativa y aporta su enlace al sidebar cuando el usuario tiene `users.view`.

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
operation=status
```

El servicio comprueba que el usuario y el rol existan antes de escribir. También impide modificar al superadministrador y que un administrador cambie su propia cuenta desde esta pantalla.

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

El controlador publicado admite inyección de `UserAdministrationService` y `RoleModel` para facilitar esta sustitución y las pruebas.

## Extensiones de la aplicación

El modelo estándar desactiva mediante `updateAuthUser()`, que rota el token y su fecha en la misma escritura que el estado. Así invalida los enlaces de correo anteriores; reactivar no los recupera. Un repositorio propio debe mantener esa garantía y comunicar escrituras fallidas al servicio.

Al desactivar, `UserAdministrationService` revoca todas las sesiones registradas del usuario y bloquea el alta de otras nuevas. Al reactivar, habilita de nuevo el registro; las sesiones anteriores no reaparecen. No se consulta la base de datos desde cada heartbeat. Si una aplicación añade eliminación de usuarios, debe llamar igualmente a `ActiveSessionRegistry::revokeUser()` dentro de ese flujo.

Asignar otro rol revoca las sesiones sin bloquear la cuenta, de modo que el siguiente acceso cargue la identidad vigente. Los cambios meramente descriptivos no deben cerrar sesiones.

Áreas, perfiles, membresías, verificación de identidad, bots y métricas pertenecen a cada proyecto. Pueden añadirse mediante un repositorio propio, filtros adicionales y vistas publicadas sin cambiar el núcleo.

La creación manual de cuentas y el cambio administrativo de contraseñas no forman parte del módulo actual. Si una aplicación los implementa, debe exigir permisos separados, registrar la operación y activar `force_password_change` cuando entregue una contraseña temporal.
