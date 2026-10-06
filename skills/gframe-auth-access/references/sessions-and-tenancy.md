# Sessions, Revocation, and Tenant Context

Use this when changing login state, password recovery, roles, memberships or a tenant-protected operation. These contracts apply to the project's resolved GFrame version; inspect its implementation before assuming a guarantee is available.

## Identity and Storage

- Authentication middleware uses `$_SESSION['auth']['id']`, not a legacy `userID` key. `SessionManager` normalizes identity and owns login, logout and identity updates.
- `SessionManager::login()` regenerates the PHP session ID, sets CSRF state and registers the authenticated device when an `ActiveSessionRegistry` is available. Updating an identity is not a new login.
- Project session values are supplied separately through the `$projectSession` argument. The normalized auth identity does not automatically retain arbitrary profile or tenant keys. Do not assume login creates a tenant or membership.
- Initialize `SessionRuntime` through the application's existing bootstrap. For CLI operations, explicitly initialize the applicable runtime or inject an `ActiveSessionRegistry`; selecting a driver in configuration alone does not create an active registry.

| Driver | Actual guarantee and requirement |
| --- | --- |
| `database` | Uses `gframe_sessions`, hashes session IDs and checks account status plus global/user/active-tenant authorization versions during a read. Authenticated writes update an existing live session instead of recreating a revoked one. Requires the managed auth schema and its migrations. |
| `redis` | Requires phpredis, the standard account lookup when registering, atomic session ownership/presence checks and version publication. It does not query account status on every read; account changes must use the revocation/blocking workflow. Use a separate prefix per project. |
| `native` | Uses PHP's configured handler. GFrame does not provide multidevice revocation or managed authorization-version checks through this driver. Do not promise managed-session guarantees. |

Use the configured session driver; do not silently replace it to make a test or feature work. Existing authenticated sessions must not be resurrected by an in-flight write after logout or revocation. This rule concerns authenticated ownership; guest session storage has its own path.

## Lifecycle

| Operation | Required existing path |
| --- | --- |
| Logout | `SessionManager::logout()` unregisters that device and clears its PHP session/cookie. Tabs sharing the same browser session share this logout. |
| Password change | `SelfAccountService` changes the password and revokes managed sessions without blocking new login. |
| Password reset by token | `AuthModel::resetPassword()` changes the password/token and calls `revokeUser()` through its injected registry or `SessionRuntime::registry()`. Verify the registry is available for the intended environment. |
| Global role assignment | `RolePermissionService` revokes the user's managed sessions. Tenant membership changes follow version invalidation instead. |
| Suspend or deactivate | Standard account services update account state and revoke sessions with `revokeUser($userID, true)`. A custom deletion path must revoke as part of its own operation. |
| Reactivate | The account service restores account state and calls `allowUser()`. `allowUser()` alone does not update `users.status` or recover old sessions. |
| Change a role's permission template | Increment/publish `roles.security_version`; managed sessions reload affected authorization without logout. |
| Change a user's overrides or tenant membership | Increment/publish `users.authorization_version`; managed sessions reload global and active tenant authorization without logout. |

Prefer existing services for these mutations. Custom SQL must preserve the same version and revocation effects. Publish a user authorization version after committing a custom tenant-creation transaction.

## Tenant Selection Is Not Authorization

`can:*` uses global permissions when `TENANT` and `TENANT_TABLE` are absent, and tenant permissions when both are configured. Defining just one is invalid when the tenant mode is evaluated. The protected superadministrator bypass is handled before ordinary tenant permission resolution; it does not replace a module's scope validation.

`TenantContextResolver::resolve($params)` inspects route parameters, POST, GET, REQUEST, the session root, normalized auth data and the legacy `tenantID` session key. It recognizes the configured key and `tenant_id`; `project_id` is recognized only when it is the configured key. Present IDs must be positive integers or decimal strings accepted by the resolver and must all agree. Missing, invalid or conflicting identities return `0`.

`TenantContextResolver::active($session)` additionally requires an identity already in session: it returns `null` when absent and throws on a mismatch. Multimedia uses this method for its tenant scope. Do not claim every module uses it; inspect each module's current scope path.

An ID identifies a candidate tenant; permissions require an explicit active membership and the applicable role template/overrides. Auth registration and installation do not create memberships. The generic `tenants` schema has no owner column; the application's tenant-creation flow establishes its owner membership atomically, using server-verified user and tenant IDs.

For switching tenants, use an authorized project flow that validates the target membership and updates any duplicated active-session keys coherently before the next tenant-protected action. Do not fix a mismatch by trusting a submitted ID or skipping middleware. `RolePermissionService` caches only the active tenant's authorization; refreshing global permissions must not overwrite the active tenant with the global marker `0`.

## Verification

Check global mode, matching tenant IDs, conflicting request/session IDs, invalid IDs, missing session scope and an unrelated user without membership. For managed sessions, check reset revocation, an unaffected other user, logout followed by a stale write, suspended login and permission refresh without logout. Do not interpret a database-driver test as verification of a real Redis deployment.

## Full Guides from the Matching Package

This reference is included when the auth skill is copied. For full SQL, configuration and extension examples, read `docs/sesiones.md`, `docs/permisos.md` and `docs/modulos-runtime.md` from the resolved GFrame package, not from `../../../docs` relative to a global skill directory.

In the framework checkout, use its root. In a consuming project, resolve the package path from Composer installation metadata and its configured vendor directory; do not assume a fixed `packages/` path or the newest global checkout. If the project's Composer loader is already available, `Composer\InstalledVersions::getInstallPath('gorvet/gframe')` supplies that installed package path. Do not start the application or print secrets merely to locate documentation.
