# Routing Lifecycle

## Route Declaration

Routes are declared with `Route::get()`, `Route::post()`, `Route::put()`, or `Route::delete()`.

The controller target uses:

```php
'admin/project/ProjectController@index'
```

## RouteBuilder Conventions

Current RouteBuilder stores:

- `controller`
- `action`
- `middleware`
- `excludedMiddleware`
- `templateName`
- `view`
- `permission`
- `context`
- `type`

Useful route modifiers include:

- `middleware([...])`
- `excludeMiddleware([...])`
- `template('admin')`
- `view('projectIndex')`
- `context([...])`
- `noAction()`
- `noRefreshSession()`

## Transport Type Inference

RouteBuilder infers the route type from the declaring file:

- web
- ajax
- api
- sse
- webhook
- system

This means route placement is part of behavior, not just organization.

## Runtime Flow

High-level flow:

1. route is matched by Router
2. middleware chain runs
3. controller action runs, unless route uses `noAction()`
4. for web routes, Render resolves view and template
5. for ajax or api routes, returned arrays are serialized to JSON
6. for sse routes, controller may stream directly

The application loads this runtime through Composer and `core/Load.php`, which delegates startup to `GFrame\Foundation\Bootstrap`.

## Naming and Inference

If template or view is omitted, Router and Render rely on controller path and action name to infer them.

Do not break:

- controller path naming
- module folder naming
- view filename conventions
- template naming conventions
