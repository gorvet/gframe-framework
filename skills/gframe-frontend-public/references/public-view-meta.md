# Public View and Meta Rules

## Public View Locations

Common public view families live under:

- `app/views/home/`
- `app/views/slug/`
- `app/views/personalizados/`
- other non-admin view folders resolved by web routes

These are application paths, not mandatory module source locations. Framework skeleton originals live under `resources/skeleton/`; distributable module originals follow their `module.php` runtime root, commonly `resources/modules/<module>/application/app/views/`. Render resolves project overrides before runtime originals through `ModuleRuntime::file`. Choose the source belonging to the authorized task.

## Meta File Pattern

Use the group and view meta pattern within the full rendering layers:

- group meta: `<group>.group.meta.php`
- view meta: `<viewName>.meta.php`

`Meta::reset` first loads `config/meta/global.meta.php`. Render combines the resolved template meta, sorted `app/views/templates/meta/<template>/*.meta.php` extensions, group meta and view meta, then applies them to Meta. The group name is the last segment of `relativePath`; do not infer it from the public URL alone.

In Render, `metaTags` merges by key, `schema` uses recursive replacement, and asset lists append while removing duplicates. A view meta is not a blanket replacement for shared assets. `js` loads in the footer; `hjs` is the separate header-script list.

Public meta files usually carry:

- `metaTags`
- `css`
- `js`
- `hjs` when an asset actually needs the header
- `schema`
- `sitemap`

## Content-Driven Meta

Slug or content-driven pages may rely on `$data` and `$routeParams` inside the meta file.

Guard optional values carefully so the meta file remains safe when:

- the page is rendered normally
- the meta file is read in sitemap or other framework contexts

Sitemap and Llms directly require `app/views/<relative>/<view>.meta.php`; they do not run the page controller or reproduce Render's template/group/runtime resolution. Keep index contracts independent of `$data`, session state and page-only variables. A group-only `sitemap` entry or a runtime-only view meta is not automatically discovered by those generators.

## Trace the Existing Home

In the framework skeleton, `config/routes/routes_web.php` registers the empty GET path with `home/HomeController@index`, template `home` and view `homeIndex`. It adds `auth` when `app.public` is false, so a home template alone does not make a route public or indexable.

The controller returns an empty array; the view is `app/views/home/homeIndex.php`, and `home.group.meta.php` supplies the current home assets and WebSite schema. `app/views/templates/homeTemplate.php` outputs the content between the shared header and footer. The skeleton has no `homeIndex.meta.php` by default; add a page-specific meta only when the task needs one, preserving the group assets.

For code and payload names, use the shared [naming reference](../../gframe-core-architecture/references/naming-conventions.md). For DOM IDs, labels and selector compatibility, use the canonical [view naming rules](../../gframe-frontend-admin/references/view-form-structure.md#field-names-and-selectors); do not impose the admin layout on a public page.
