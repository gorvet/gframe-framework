---
name: gframe-backend
description: Build, refactor, and debug backend HTTP code in GFrame applications, including routes, controllers, services, repositories, ORM models, authentication, global or tenant permissions, and stable response contracts.
---

# GFrame Backend

Use this when the task is mainly backend HTTP flow: routes, middleware, controllers, services, models, payload contracts, partial HTML rendering, or tenant-aware protected endpoints.

If the task is mostly framework structure, also use `gframe-core-architecture`.
If the task is mostly model and ORM work, prefer `gframe-orm-models`.

## Read Order

1. [references/framework-conventions.md](references/framework-conventions.md)
2. [references/backend-model-boundary.md](references/backend-model-boundary.md)

## Workflow

1. Identify the route file and route type in `config/routes`.
2. Confirm middleware scope before touching controller logic.
3. Keep controller as the HTTP boundary: parse request, validate required fields, sanitize and cast, orchestrate.
4. Move reusable non-trivial orchestration to services.
5. Keep persistence and ORM-heavy work in models.
6. Return stable payload keys expected by Router and JS.
7. For list or fragment flows, render HTML in PHP and attach it as `html`.
8. Keep user-facing copy in backend payloads or rendered PHP when the frontend needs to display it.

## Hard Rules

- Do not modify the GFrame repository or `vendor/gorvet/gframe` for project-specific backend work.
- If backend requirements do not fit existing framework conventions, stop and call out the mismatch before changing code.
- Prefer adapting controllers, models, services, routes in `config/routes`, and database schema before proposing any framework patch.
- Routes live in `config/routes/*.php`, not `routes/*.php`.
- Route type is inferred by the declaring file: `routes_web`, `routes_ajax`, `routes_api`, `routes_sse`, `routes_webhook`, `routes_system`.
- Middleware decides access scope. Do not move `auth`, `admin`, or `can:*` permission logic into models.
- Protected `can:*` flows need tenant context only when tenancy is configured. Global applications resolve permissions from the authenticated role or stored global permission row.
- Keep framework services schema-independent through contracts and project repositories, as used by authentication and account management.
- Admin-only pages and endpoints belong under admin routes and should stay behind `auth` plus `admin` or `can:*`.
- Controller validates required request fields and sanitizes free text before calling model or service.
- Model must not read raw `$_POST`, `$_REQUEST`, or generic form state.
- Model should not redo generic required-field validation already enforced by controller.
- Use services for filesystem work, external APIs, reusable orchestration, or logic that should not live in controller/model.
- `code` is part of the backend-frontend contract. Emit canonical lowercase codes, preferably `snake_case`, such as `empty_field`, `not_found`, `bad_json`, `to_login`, and `to_reload`.
- Do not invent mixed conventions like camelCase for some modules and lowercase for others.
- Do not lowercase or normalize controller/model business codes in Router `ajax` or `api` output. Payload codes must arrive at JS exactly as emitted by the module.
- Numeric `4xx/5xx` codes may exist as framework aliases for transport and error resolution, but business/module payloads should prefer canonical string codes.
- Router handles JSON output for `ajax` and `api`; controllers should return arrays instead of echoing JSON manually.
- For list refresh flows, prefer server-rendered partial HTML instead of assembling markup in JS.
- If a frontend flow needs user-facing copy, return it from controller/model payloads or render it in PHP instead of forcing the module JS to invent texts locally.
- Keep GET page-clamping redirects only on GET requests.
- Preserve RouteBuilder and Render naming inference. Do not break controller, model, view, or template alignment.

## Controller Responsibilities

- Read route params, `$_POST`, and `$_REQUEST` only at the controller boundary.
- Cast numeric ids immediately.
- Sanitize user text with framework helpers before passing it deeper.
- Return early on invalid request state with `status`, `code`, and `message`.
- Compose final payloads for the frontend, including `html` when partial replacement is expected.
- Keep side effects in controller or service when they orchestrate multiple collaborators.

## Model Responsibilities

- Own query and write logic through ORM.
- Accept normalized arguments from controllers and services.
- Enforce data-level invariants close to persistence when needed.
- Return structured arrays with stable keys.
- Avoid route-level permission checks, session auth checks, and raw request parsing.

## Response Contract

Use stable keys already common in the framework:

- `status` is mandatory in success and error payloads.
- `message` is included when it adds user or caller value.
- `code` is used for domain, validation, redirect, or transport-aware handling.
- `data` is used for list, detail, and action payloads.
- `meta` is used for pagination, filter echoes, and auxiliary state.
- `html` is used when backend renders a partial for AJAX replacement.
- Prefer the backend as the source of user-visible messages or labels when AJAX flows need dynamic copy.

Do not rename these keys casually.

### Code Convention

- Prefer lowercase `snake_case` for all emitted payload codes across controllers, services, models, middleware, and core helpers.
- Keep code names stable and exact. Good examples: `empty_field`, `invalid_token`, `not_exists`, `user_exists`, `service_unavailable`.
- Single-word lowercase codes are acceptable when they are truly canonical, but do not mix them with camelCase aliases for the same intent.
- If the framework needs compatibility with numeric aliases like `404` or `503`, keep that alias handling inside core error resolution, not inside module payload mutation.
- When refactoring legacy modules, migrate producers to canonical codes instead of teaching JS to guess or normalize variants.

## Common Recipes

### List Endpoint

1. Read filters and requested page.
2. Call model list method with `itemsPerPage`.
3. If request method is GET and model clamped the page, redirect to normalized querystring.
4. Render the list partial with `ob_start()` plus `include`.
5. Return `status`, `data`, `meta`, and `html`.

### Save Endpoint

1. Validate required fields in controller.
2. Sanitize text and cast ids.
3. Call model or service.
4. If the module expects fresh rendered HTML, append `html` from a list or detail partial.

### Delete or Toggle Endpoint

1. Cast target id immediately.
2. Validate allowlists or action intent in controller.
3. Delegate mutation to model.
4. Preserve response keys expected by existing JS.

## Partial Rendering Pattern

For AJAX modules that replace sections of the page, render in PHP:

```php
ob_start();
include realpath(ABSPATH . 'app/views/admin/module/_list.php');
$response['html'] = ob_get_clean();
return $response;
```

If the frontend already expects backend-rendered fragments, do not move that markup into JS templates.

## Anti-Patterns

- Echoing ad-hoc JSON inside standard ajax controllers.
- Revalidating route permissions in models.
- Reading superglobals directly inside models.
- Returning ad-hoc keys instead of `status`, `message`, `code`, `data`, `meta`, `html`.
- Building HTML strings inside JS for modules that already use backend partials.
- Making JS guess or hardcode business copy that could be emitted by controller, model, or rendered partial.
- Skipping `reset()` and leaking ORM query state between intents.
- Writing direct SQL when ORM methods already cover the operation.

## Final Self-Check

1. Route points to an existing controller class and public action.
2. Middleware matches public, guest, auth, admin, or tenant-protected intent.
3. Controller owns validation and sanitization.
4. Model or service owns persistence and reusable orchestration.
5. Response payload keys match the existing frontend contract.
6. Partial include paths resolve through `realpath(ABSPATH . '...')`.
