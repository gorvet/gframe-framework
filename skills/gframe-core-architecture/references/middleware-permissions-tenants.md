# Middleware, Permissions, and Tenants

## Project Rule

- Do not modify GFrame middleware or routing to fit one project's schema or business rules.
- Shared fixes belong in the GFrame repository; project adapters belong in the application's `app/` and `config/` layers.
- Never patch `packages/gframe/framework` or rebuild a duplicated local core.

## Middleware Roles

Current middleware names include `guest`, `auth`, `admin`, `role:*`, `can:*`, CSRF, honeypot, and transport guards.

Authentication middleware reads the normalized `$_SESSION['auth']` identity as its only identity contract.

## Permission Modes

`can:*` operates in two modes:

- global permissions when tenancy configuration is omitted;
- tenant permissions when tenant key and tenant table are both configured.

Only tenant mode requires a tenant identifier from route parameters or request data. Defining only one tenancy setting is invalid.

Roles are permission templates. `RolePermissionService` resolves effective permissions from the session, including global and tenant-specific user overrides.

## Administrative Hierarchy

- Installation creates one unique superadministrator as the first user.
- `superadministrator` is a protected system role and bypasses `admin` and `can:*` checks.
- Additional administrators are optional and never become superadministrators implicitly.
- `admin.access` defines which ordinary roles pass the `admin` middleware.
- The superadministrator account must remain protected from self-deactivation and delegated administration.

## Tenant Assignment

Tenant mode requires an explicit active row in `tenant_memberships`. Owning a project record does not grant framework permissions implicitly.

Auth creates users and a global role only. The application module that creates a tenant must also create its `owner` membership atomically. The generic `tenants` table has no owner column; do not infer ownership from submitted IDs. An active owner may manage `gestor` memberships, and a gestor may leave. See [roles, permissions, and memberships](../../../docs/permisos.md) for installation variants, ownership verification, and the transaction example.

## Boundary Rule

- middleware decides request access;
- controllers orchestrate after access is granted;
- models do not enforce route middleware;
- application policies handle domain-specific visibility, areas, and editorial rules.
