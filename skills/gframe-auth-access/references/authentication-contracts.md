# Authentication MVC

Auth-ui, self-account and user-admin keep their original controllers and views below application/app/{controllers,views}/<module>. Routes declare module('<module>'); installation creates empty app/{controllers,models,services,views}/<module> folders. Project controllers use App\\Controllers\\AuthUi, App\\Controllers\\SelfAccount or App\\Controllers\\UserAdmin and extend GFrame\\Modules\\<StudlyModule>\\Controllers\\<Controller>. Views and meta resolve project first, then the associated module. Do not copy original files automatically or invent hooks. Public URLs and POST fields do not change; old auth/account/admin-users customizations require explicit migration. See docs/modulos-runtime.md.

The former config/auth/extensions.php factories have been removed. Construct and inject project models/services in the customized controller via parent::__construct(...); a subclass does not automatically replace new OriginalClass(). Preserve security, session, role and response contracts. The updater must not overwrite custom files or delete old project extensions. Campaigns now uses runtime files and model/controller/cron inheritance; its callback registry is removed. alerts is a frontend JS/CSS component, not a PHP MVC module; no runtime conversion or PHP inheritance migration applies. Do not confuse it with notifications, which already uses native MVC runtime.

## Framework Authentication Components

- `GFrame\Auth\AuthModel`
- `GFrame\Auth\AuthInstallationService`
- `GFrame\Auth\SelfAccountService`
- `GFrame\Auth\SessionManager`
- `GFrame\Auth\PasswordPolicy`

`PasswordPolicy` reside en `resources/modules/password-utils/src/` y es la regla obligatoria de aceptación (8–72 bytes UTF-8 por defecto). El módulo `password-utils` replica ese límite en el navegador; su puntuación visual no decide si una contraseña es válida. La generación de cliente requiere `window.crypto.getRandomValues()`.
- `GFrame\Auth\TokenManager`
- `GFrame\Auth\RolePermissionService`

## Standard Models

Authentication follows `AuthController -> AuthModel -> ORM`, using the original Base Confías/Bebots operations `registerAcount`, `login`, `validateAcount`, `recoveryAcount`, `resetPassword`, and `verifyAcount`. Do not restore `AuthService` or put these flows in `UserModel`. Keep the normalized schema and security protections. `UserModel` handles account and user administration persistence; `RoleModel` handles roles and memberships. Neither authentication nor its registration flow creates tenants or project resources. If an application authenticates against an external API or identity server, implement a dedicated integration at the application boundary rather than complicating the standard path.

Keep project-specific profile data outside the framework `users` table. The base My Account flow works through `UserModel`; applications add their own related model for names, phones, avatars, or domain-specific profile fields.

The admin display falls back to the email local part before `@`, without persisting that value as a name. Public auth JS handles `already_logged` through the guest web route. `updateAuthUser()` rejects missing rows but accepts unchanged writes. Suspending or disabling through this method rotates the mail token and timestamp in the same write, including administrative deactivation. Recovery does not issue links for blocked accounts; verification/reset also reject them.

PHP sessions use `SessionRuntime` with `DatabaseSessionHandler` or `RedisSessionHandler`. Successful login explicitly creates the authenticated session; subsequent writes must update only an existing session, never recreate one removed by logout or revocation. The database driver uses only `gframe_sessions` and reads `users.status` in the same query as authorization versions; it does not need `gframe_session_users`. Redis uses atomic presence checks on its session keys and a temporary block key for suspended accounts. Suspension, administrative deactivation, and self-deactivation call `ActiveSessionRegistry::revokeUser(..., true)`; reactivation calls `allowUser()`. Role and password changes revoke sessions without blocking the account. Permission changes increment the role security version; middleware refreshes the authorization snapshot once on the next request without logging the user out. Do not query the user table from heartbeat or middleware separately on every request. Any application-specific user deletion flow must revoke that user's sessions as part of the mutation. See [sessions](../../../docs/sesiones.md) for the full lifecycle.

Roles store permission templates in `roles.permissions_json`. `UserPermissionService` applies global overrides in `users.permission_overrides_json` and tenant overrides and role assignments in `tenant_memberships`. These changes increment `users.authorization_version`; managed sessions refresh their cached global and active tenant authorization on the next operation without logout.

Auth registration and installation never create tenant memberships. The application's tenant-creation flow inserts the first `owner` membership using the authenticated user ID and the newly created tenant ID in one transaction; publish the user authorization version after commit. For an existing tenant, verify ownership against an independent project source first. The default `tenants` table has no owner column. Global applications do not need memberships. See [roles, permissions, and memberships](../../../docs/permisos.md) for the concrete workflow and SQL.

The modules `auth-ui`, `self-account` and `user-admin` provide the standard controllers, routes and views. Applications may override their presentation and keep project-specific messages, email templates, redirects, area assignments and legacy session keys at the application boundary.

The normalized `$_SESSION['auth']` identity is the framework source of truth and contains `id`, `email`, `name`, `role_id`, and `role`. `name` may come from an application profile and is not required in the framework users table. Do not add a duplicate superadministrator boolean flag.

When extracting an application's Auth UI, preserve its HTTP route names and posted field names. If a rename is unavoidable, document the old-to-new mapping, update every view and JavaScript caller, keep a compatibility alias where needed, and test the complete browser-to-controller flow before release. Never leave copied JavaScript pointing at an unpublished route.

The `redirect` response field is always relative to the application's base URL, without a leading slash or scheme (`admin`, `account`, `login`). Every JavaScript consumer prepends `site_url` exactly once. Do not mix absolute and relative values in this field. Validate `rd` server-side and give mandatory password changes priority. Email links and HTTP headers construct their full URL separately.

AuthModel returns operation data under `data`, including `user`, `first_login`, `must_change_password`, `user_id`, and internal `token` as applicable. Controllers read these keys from `data` and remove tokens before public responses. Public login exposes `data.must_change_password`; `redirect` remains response navigation metadata at the root. Keep `status` and `code` at the root like other services; operations without a payload may omit `data`.

When discussing PHP Auth with the user, explicitly identify whether the code is a view, controller, model, or service. AuthModel owns authentication operations and their persistence and response contract; AuthController adapts them for HTTP; UserModel owns account and user administration persistence. Do not describe all these layers merely as "Auth PHP".

The standard SelfAccountController integrates AccountDeactivationLifecycle only when notification-campaigns is installed. The account service still owns voluntary deactivation, not timed deletion. Default retention is 60 days and the prior warning is 72 hours, configured under auth.deactivation. Only explicitly recorded cycles qualify: legacy disabled accounts and protected superadministrators never enter deletion implicitly. Require a sent warning and the full notice period before transactional cleanup; reactivation cancels the cycle and a new deactivation restarts retention. Custom account repositories must supply their own compatible lifecycle cleanup rather than applying standard-schema deletion to project-specific data.
