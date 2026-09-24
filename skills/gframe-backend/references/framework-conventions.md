# GFrame Backend Conventions

## Core Layout

- `config/routes/` stores route declarations by transport type.
- GFrame internals live in the standalone framework repository under `src/` and are installed with Composer.
- Application `core/Load.php` is only a bootstrap bridge to `GFrame\Foundation\Bootstrap`.
- `app/controllers/` stores controllers.
- `app/models/` stores ORM models.
- `app/services/` stores reusable orchestration and subsystem logic.
- `app/views/` stores full views, partials, and meta files.
- `public/js/core/` stores shared JS helpers.
- `public/js/app/admin/` stores admin module JS.
- `public/css/app/admin/` stores admin module CSS.

## Route Files and Transport Types

Routes are declared in `config/routes/*.php`.

The current route type is inferred by `RouteBuilder` from the declaring file:

- `routes_web.php` -> `web`
- `routes_ajax.php` -> `ajax`
- `routes_api.php` -> `api`
- `routes_sse.php` -> `sse`
- `routes_webhook.php` -> `webhook`
- `routes_system.php` -> ajax-like system endpoints

Declare routes with:

```php
Route::get('admin/projects', 'admin/project/ProjectController@index');
Route::post('ajax/admin/projects/save', 'admin/project/ProjectController@save');
```

## Route to Controller Resolution

The route target string maps to:

- controller file: `app/controllers/<target>.php`
- controller class: last path segment
- action: method after `@`

Example:

- route target: `admin/project/ProjectController@index`
- controller file: `app/controllers/admin/project/ProjectController.php`
- controller class: `ProjectController`

Keep exact file and class case for Linux-safe deployments.

## Middleware and Access Scope

Common middleware names:

- `guest`
- `auth`
- `admin`
- `can:view`
- `can:edit`
- `can:add`
- `can:delete`

Access scope is decided in middleware and route config, not in models.

`can:*` protection supports global and tenant-aware applications. In tenant mode, middleware resolves tenant mainly from:

- route params keyed by `TENANT`
- `project_id`
- `tenant_id`

Missing tenant context fails only when tenancy is enabled. Global mode does not require a tenant identifier.

The superadministrator bypasses permission checks. Ordinary administrator roles are configured through `auth.administrator_roles` and remain subordinate.

## Admin, Public, and Protected Flows

- Public pages are usually plain web routes without protected middleware.
- Guest pages such as login or register use `guest`.
- Admin-only platform routes usually use `auth` plus `admin`.
- Tenant-protected workspaces usually use `auth` plus `can:*`.

Do not weaken those boundaries in controller or model code.

## Controller Boundary

Controllers are the request boundary. They should:

- read route params and request payload
- validate required fields
- sanitize text and cast ids
- call models and services
- compose final response payloads
- render partial HTML when the frontend expects `html`

## Service Boundary

Use services when logic is:

- filesystem-heavy
- external-API-heavy
- multi-step orchestration across models or helpers
- reusable across modules

Do not force all business logic into controllers if it is clearly reusable or subsystem-specific.

## Model Boundary

Models should:

- own ORM queries and writes
- receive normalized values from controllers and services
- avoid raw request parsing
- avoid route-level permission logic
- return structured arrays

## View and Partial Rendering

For ajax list or fragment flows, render HTML in backend:

```php
ob_start();
include realpath(ABSPATH . 'app/views/admin/module/_list.php');
$response['html'] = ob_get_clean();
```

For whitelisted fragments, `ViewHelper::fragmentRender()` is also used.

If the module already replaces blocks with backend HTML, keep that pattern.

## Template and View Inference

If explicit route values are omitted:

- template can be inferred from controller path
- view can be inferred as `<module><Ucfirst(action)>`

Do not rename files casually if the route depends on inference.

## Response Keys

These keys are stable across backend flows:

- `status`
- `message`
- `code`
- `data`
- `meta`
- `html`

Some endpoints use only a subset, but do not replace them with ad-hoc names unless the module already established a different contract.

## GET Clamp Behavior

List controllers that clamp pages should redirect only on GET:

```php
$isGet = ($_SERVER['REQUEST_METHOD'] === 'GET');
if ($isGet && $realPage !== $requestedPage) {
  header('Location: ' . $path . '?' . http_build_query($qs), true, 302);
  exit;
}
```
