# Autenticación

La instalación y personalización de las pantallas se documenta en [Interfaz de autenticación](auth-ui.md).

La composición de plantillas de rol y excepciones individuales se documenta en [Roles y permisos](permisos.md).

GFrame separa la autenticación, los perfiles y la autorización por roles sin abandonar el flujo MVC.

El flujo estándar es `AuthController → AuthModel → ORM`. El modelo `AuthModel` utiliza las tablas normalizadas `users` y `roles`. La tabla de usuarios no exige un nombre: los datos personales pertenecen al perfil de la aplicación.

`GFrame\Auth\AuthModel` proporciona:

- registro con normalización de correo y política de contraseña;
- verificación de cuenta;
- acceso sin revelar si falló el correo o la contraseña;
- solicitud y aplicación del restablecimiento de contraseña;
- reenvío seguro de la verificación;
- tokens aleatorios con caducidad configurable.
- expiración opcional de contraseñas y cambio obligatorio.

El acceso solo se concede cuando el estado devuelto por el adaptador coincide con el estado activo configurado. Los estados pendientes, suspendidos o desconocidos no crean una sesión.

`GFrame\Auth\SessionManager` regenera la sesión al acceder, mantiene la identidad normalizada en `$_SESSION['auth']`, admite claves transitorias del proyecto y destruye la sesión al salir. El middleware utiliza `auth.id`, `auth.role_id`, `auth.role`, `auth.permissions`, `auth.role_version` y `auth.bypass`.

Los permisos efectivos se cargan al iniciar sesión. `admin`, `role:*` y `can:*` leen esa fotografía sin consultar las tablas de autorización en cada operación. Cada rol conserva `security_version`. Al cambiar sus permisos mediante `RolePermissionService`, la versión aumenta; en la siguiente petición de cada dispositivo el manejador detecta la diferencia, recarga una sola vez los permisos actuales y actualiza su propia sesión sin cerrar el acceso.

Un cambio de rol global revoca las sesiones del usuario porque altera su identidad de autorización. Los cambios de rol por tenant y las excepciones individuales incrementan la versión de autorización del usuario y recargan la sesión en la siguiente operación.

El apartado **Mi cuenta** permite al usuario conectado consultar los datos base de su cuenta, cambiar la contraseña y desactivarla. Internamente usa `GFrame\Auth\SelfAccountService` y `UserModel`, protege directamente al superadministrador y no presupone campos de perfil como nombre, teléfono o avatar. Esos datos pertenecen al modelo de la aplicación.

`GFrame\Auth\RolePermissionService` administra las plantillas de `roles.permissions_json`. `UserPermissionService` administra las excepciones de `users` y las membresías de `tenant_memberships`. Consulte [Roles, permisos y membresías](permisos.md).

Toda instalación debe crear el rol protegido `superadministrator` y asignarlo al primer usuario. Ese rol pasa `admin` y `can:*` sin asignaciones adicionales, no puede concederse a otra cuenta, degradarse ni eliminarse. Los demás roles obtienen capacidades desde su plantilla JSON; `admin.access` concede acceso al middleware `admin`.

`GFrame\Auth\AuthInstallationService` crea la primera cuenta mediante `UserModel`, la deja verificada y le asigna el rol `superadministrator`. Rechaza nuevas ejecuciones cuando ya existe algún usuario.

La expiración de contraseñas está desactivada por defecto. Cuando se activa, `password_changed_at` y `force_password_change` redirigen al destino configurado en `auth.password_change_redirect`, que por defecto es la pantalla de cambio de contraseña de `self-account`.

Los esquemas de referencia para MySQL y SQLite están en `resources/database/schema`. El esquema normalizado no utiliza `is_super_admin` ni listas configurables de roles administrativos.

Las vistas, mensajes de correo, perfiles, áreas, redirecciones y reglas particulares permanecen en cada aplicación.

## Separación MVC y migración

El modelo `AuthModel` gestiona las operaciones `registerAcount()`, `login()`, `validateAcount()`, `recoveryAcount()`, `resetPassword()` y `verifyAcount()`. Ejecuta las consultas de autenticación directamente mediante ORM, sin `AuthService` ni dependencia de `UserModel`. No crea tenants, negocios, planes, wallets, suscripciones ni membresías.

Se conservan el esquema normalizado `users`/`roles`, la política de contraseña, la caducidad de tokens, la recuperación sin revelar cuentas y el contrato `status`/`code`/`data`. El controlador mantiene los correos, las redirecciones y la creación de sesión mediante `SessionManager`; no se copian las claves de sesión ni las reglas particulares de los proyectos originales.

Cambio incompatible: sustituir `new AuthService(new UserModel())` por `new AuthModel()`. Los métodos anteriores `register`, `authenticate`, `verify`, `requestRecovery` y `requestVerification` pasan respectivamente a `registerAcount`, `login`, `validateAcount`, `recoveryAcount` y `verifyAcount`. `resetPassword` conserva su nombre. Actualizar conjuntamente controladores y pruebas; no quedan alias del servicio eliminado.
