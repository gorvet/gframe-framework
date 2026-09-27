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

Global permissions use `roles`, `permissions`, and `role_permissions` when the normalized identity includes `role_id`. Tenant permissions retain the tenant assignment layer and its application templates.

## Administrative Hierarchy

- Installation creates one unique superadministrator as the first user.
- `superadministrator` is a protected system role and bypasses `admin` and `can:*` checks.
- Additional administrators are optional and never become superadministrators implicitly.
- `admin.access` defines which ordinary roles pass the `admin` middleware.
- The superadministrator account must remain protected from self-deactivation and delegated administration.

## Owner Fallback

In tenant mode, a tenant owner without a stored permission row may receive the configured owner fallback. This behavior does not apply to global permissions.

## Boundary Rule

- middleware decides request access;
- controllers orchestrate after access is granted;
- models do not enforce route middleware;
- application policies handle domain-specific visibility, areas, and editorial rules.
