# Autenticación

GFrame separa la autenticación, los perfiles y la autorización por roles sin abandonar el flujo MVC.

El flujo estándar es `AuthController → AuthService → UserModel → ORM`. `UserModel` utiliza las tablas normalizadas `users` y `roles`. La tabla de usuarios no exige un nombre: los datos personales pertenecen al perfil de la aplicación.

`GFrame\Auth\AuthService` proporciona:

- registro con normalización de correo y política de contraseña;
- verificación de cuenta;
- acceso sin revelar si falló el correo o la contraseña;
- solicitud y aplicación del restablecimiento de contraseña;
- reenvío seguro de la verificación;
- tokens aleatorios con caducidad configurable.
- expiración opcional de contraseñas y cambio obligatorio.

El acceso solo se concede cuando el estado devuelto por el adaptador coincide con el estado activo configurado. Los estados pendientes, suspendidos o desconocidos no crean una sesión.

`GFrame\Auth\SessionManager` regenera la sesión al acceder, mantiene la identidad normalizada en `$_SESSION['auth']`, admite claves transitorias del proyecto y destruye la sesión al salir. El middleware utiliza `auth.id`, `auth.role_id` y `auth.role` como identidad principal.

Las decisiones de autorización vuelven a resolver el rol almacenado mediante `RoleModel`; cambiar un rol o permiso tiene efecto sin confiar en una copia antigua de la sesión.

El apartado **Mi cuenta** permite al usuario conectado consultar los datos base de su cuenta, cambiar la contraseña y desactivarla. Internamente usa `GFrame\Auth\SelfAccountService` y `UserModel`, protege directamente al superadministrador y no presupone campos de perfil como nombre, teléfono o avatar. Esos datos pertenecen al modelo de la aplicación.

`GFrame\Auth\RolePermissionService` administra roles, permisos y asignaciones mediante `RoleModel`, que utiliza `users.role_id`, `roles`, `permissions` y `role_permissions`.

Toda instalación debe crear el rol protegido `superadministrator` y asignarlo al primer usuario. Ese rol pasa `admin` y `can:*` sin asignaciones adicionales, no puede concederse a otra cuenta, degradarse ni eliminarse. Los demás roles obtienen capacidades mediante `role_permissions`; el permiso `admin.access` concede acceso al middleware `admin`.

`GFrame\Auth\AuthInstallationService` crea la primera cuenta mediante `UserModel`, la deja verificada y le asigna el rol `superadministrator`. Rechaza nuevas ejecuciones cuando ya existe algún usuario.

La expiración de contraseñas está desactivada por defecto. Cuando se activa, `password_changed_at` y `force_password_change` permiten exigir la renovación sin bloquear el acceso a la pantalla de cambio de contraseña.

Los esquemas de referencia para MySQL y SQLite están en `resources/database/schema`. El esquema normalizado no utiliza `is_super_admin` ni listas configurables de roles administrativos.

Las vistas, mensajes de correo, perfiles, áreas, redirecciones y reglas particulares permanecen en cada aplicación.
