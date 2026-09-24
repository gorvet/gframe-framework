---
name: gframe-core-architecture
description: Understand and modify the standalone GFrame framework and its application integration, including Composer bootstrap, configuration, routes, middleware, permissions, authentication, rendering, and global or tenant-aware execution.
---

# GFrame Core Architecture

Use this when the task is about framework structure or cross-cutting conventions rather than one module's business logic.

Examples:

- understanding how route files are organized
- changing middleware or permission behavior
- adjusting template, view, or meta resolution
- working on admin, public, guest, or protected route conventions
- tracing request lifecycle through Router, Middleware, and Render

## Read Order

1. [references/folder-map.md](references/folder-map.md)
2. [references/routing-lifecycle.md](references/routing-lifecycle.md)
3. [references/middleware-permissions-tenants.md](references/middleware-permissions-tenants.md)
4. [references/view-meta-render.md](references/view-meta-render.md)

## Workflow

1. Identify whether the change belongs to the framework repository or to an application adapter.
2. For request behavior, start from the application's route file under `config/routes`.
3. Trace the lifecycle: RouteBuilder -> Router -> Middleware -> controller -> Render or JSON response.
4. Confirm whether the route is public, guest, admin-only, or tenant-protected.
5. Compare the project need against existing core conventions before proposing any framework change.
6. If the project does not fit the core, stop and state the mismatch before deciding on a solution.
7. Preserve naming and inference conventions before changing structure.
8. Only then update module code that depends on the framework behavior.

## Hard Rules

- Framework source lives in the standalone `gframe-framework/src` tree and is consumed through Composer.
- An application normally keeps only `core/Load.php` as its bootstrap bridge. Never recreate or patch a duplicated local core.
- Never edit `vendor/gorvet/gframe` as the source of a fix. Make shared changes in the GFrame repository and update the application's Composer lock.
- If project schema, routes, payloads, or naming do not fit the core, adapt the project layer (`app/`, `config/`, views, controllers, models, services, database) instead of patching the framework.
- Do not add project-specific constants, aliases, fallbacks, or exceptions to core behavior just to make one project fit.
- If there is a mismatch between project requirements and core conventions, surface it before coding and ask for direction rather than silently changing the framework.
- Routes live in `config/routes`, split by transport type.
- Route type is inferred by the file where it is declared.
- Middleware is the first-class place for auth, guest, admin, tenant, and `can:*` authorization checks.
- `can:*` works globally when tenancy is not configured and requires tenant context only when both the tenant key and table are configured.
- The superadministrator bypasses permission checks. Configured administrator roles are subordinate and do not become superadministrators.
- Owner fallback permission rows are currently created automatically when a tenant owner has no explicit permission row yet.
- Do not move framework permission behavior into controllers or models unless the change is explicitly framework-level.
- Keep RouteBuilder and Render inference compatible unless the task is explicitly about changing that inference.
- Keep framework conventions generic. Do not bake project-specific domain logic into core architecture changes.

## Use With Other Skills

- Use `gframe-backend` when the task moves from framework rules into controller or service implementation.
- Use `gframe-orm-models` when the task is mostly about models and ORM behavior.
- Use `gframe-frontend-admin` when the task is mainly about admin views, meta assets, and AJAX UI contracts.
