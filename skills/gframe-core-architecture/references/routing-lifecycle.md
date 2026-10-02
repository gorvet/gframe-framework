# Routing Lifecycle

## Route Declaration

Routes are declared with `Route::get()`, `Route::post()`, `Route::put()`, or `Route::delete()`.

The controller target uses:

```php
'admin/project/ProjectController@index'
```

## RouteBuilder Conventions

Runtime modules declare `->module('self-account')`. RouteBuilder stores it as `sourceModule`, separately from the historical permission `module` value. Router infers sourceModule from the controller's last folder only if it names an installed runtime module. Controller/view/template inference is unchanged. Original and project classes have different namespaces, with project first and module fallback. Read `docs/modulos-runtime.md` before converting a module. All catalog MVC modules use this convention; visual assets, mail templates and CLI entry points may remain published without MVC runtime. Check active runtime membership with `ModuleRuntime::has()`, never by the presence of a copied original view. Customize controllers through PHP inheritance and constructor injection, not MVC callback registries or factories.

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

Admin-panel, error-pages and heartbeat-client also use runtime originals. Dashboard routes declare admin-panel, error routes built by ErrorResponder declare sourceModule=error-pages, and ajax/heartbeat declares heartbeat-client without changing its auth/CSRF/noRefreshSession settings. Add heartbeat channels through controller inheritance and parent::__construct(), not through another callback configuration registry. Public assets and route files remain published.

High-level flow:

1. route is matched by Router
2. middleware chain runs
3. controller action runs, unless route uses `noAction()`
4. for web routes, Render resolves view and template
5. for ajax or api routes, returned arrays are serialized to JSON
6. for sse routes, controller may stream directly

The application loads this runtime through Composer and `core/Load.php`, which delegates startup to `GFrame\Foundation\Bootstrap`.

Before URL normalization and route execution, Router accepts only trusted server error parameters `GFRAME_SERVER_ERROR` (Nginx FastCGI) or `REDIRECT_STATUS` (Apache), restricted to 403/404/500/503. Reuse ErrorResponder and the existing error views/transport responses; never consume `error_code` from query strings or HTTP headers. See `docs/servidores-web.md` for the managed Nginx server fragment, PHP entry-point restrictions and PHP-FPM outage limits.

Server configuration must serve physical files only from explicitly authorized public locations. The skeleton permits public/ and the standard uploads library sources; user/tenant folders do not imply download authorization. Other upload sources and download/downloads directories are denied directly. Private files and exports must be served through controlled routes, never by opening storage or allowing every existing root file. See docs/servidores-web.md before adding a public source.

## Naming and Inference

If template or view is omitted, Router and Render rely on controller path and action name to infer them.

Do not break:

- controller path naming
- module folder naming
- view filename conventions
- template naming conventions
