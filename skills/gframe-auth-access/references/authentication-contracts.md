# Authentication MVC

## Framework Services

- `GFrame\Auth\AuthService`
- `GFrame\Auth\AuthInstallationService`
- `GFrame\Auth\SelfAccountService`
- `GFrame\Auth\SessionManager`
- `GFrame\Auth\PasswordPolicy`

`PasswordPolicy` reside en `resources/modules/password-utils/src/` y es la regla obligatoria de aceptación (8–72 bytes UTF-8 por defecto). El módulo `password-utils` replica ese límite en el navegador; su puntuación visual no decide si una contraseña es válida. La generación de cliente requiere `window.crypto.getRandomValues()`.
- `GFrame\Auth\TokenManager`
- `GFrame\Auth\RolePermissionService`

## Standard Models

Use `UserModel` and `RoleModel` with the standard schema. Controllers call services for authentication or authorization rules, and services call these ORM models. Do not add a repository layer around them. If an application authenticates against an external API or identity server, implement a dedicated integration at the application boundary rather than complicating the standard path.

Keep project-specific profile data outside the framework `users` table. The base My Account flow works through `UserModel`; applications add their own related model for names, phones, avatars, or domain-specific profile fields.

The admin display falls back to the email local part before `@`, without persisting that value as a name. Public auth JS handles `already_logged` through the guest web route. `updateAuthUser()` rejects missing rows but accepts unchanged writes. Suspending or disabling through this method rotates the mail token and timestamp in the same write, including administrative deactivation. Recovery does not issue links for blocked accounts; verification/reset also reject them.

PHP sessions use `SessionRuntime` with `DatabaseSessionHandler` or `RedisSessionHandler`. Successful login explicitly creates the authenticated session; subsequent writes must update only an existing session, never recreate one removed by logout or revocation. The database driver uses only `gframe_sessions` and reads `users.status` in the same query as authorization versions; it does not need `gframe_session_users`. Redis uses atomic presence checks on its session keys and a temporary block key for suspended accounts. Suspension, administrative deactivation, and self-deactivation call `ActiveSessionRegistry::revokeUser(..., true)`; reactivation calls `allowUser()`. Role and password changes revoke sessions without blocking the account. Permission changes increment the role security version; middleware refreshes the authorization snapshot once on the next request without logging the user out. Do not query the user table from heartbeat or middleware separately on every request. Any application-specific user deletion flow must revoke that user's sessions as part of the mutation. See [sessions](../../../docs/sesiones.md) for the full lifecycle.

Roles store permission templates in `roles.permissions_json`. `UserPermissionService` applies global overrides in `users.permission_overrides_json` and tenant overrides and role assignments in `tenant_memberships`. These changes increment `users.authorization_version`; managed sessions refresh their cached global and active tenant authorization on the next operation without logout.

Auth registration and installation never create tenant memberships. The application's tenant-creation flow inserts the first `owner` membership using the authenticated user ID and the newly created tenant ID in one transaction; publish the user authorization version after commit. For an existing tenant, verify ownership against an independent project source first. The default `tenants` table has no owner column. Global applications do not need memberships. See [roles, permissions, and memberships](../../../docs/permisos.md) for the concrete workflow and SQL.

The modules `auth-ui`, `self-account` and `user-admin` provide the standard controllers, routes and views. Applications may override their presentation and keep project-specific messages, email templates, redirects, area assignments and legacy session keys at the application boundary.

The normalized `$_SESSION['auth']` identity is the framework source of truth and contains `id`, `email`, `name`, `role_id`, and `role`. `name` may come from an application profile and is not required in the framework users table. Do not add a duplicate superadministrator boolean flag.
