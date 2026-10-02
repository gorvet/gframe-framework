# View, Meta, and Render Flow

## Render Role

For a route associated with an installed runtime module, file lookup uses app first and the module's mirrored app root second. Apply the same sourceModule to controller, view, group/view meta and footer partials. Templates remain under views/templates; shared providers declare runtime.templates. Do not replace naming inference or search unrelated modules by basename. Direct include paths do not gain fallback automatically; use ModuleRuntime::file for module partials. See docs/modulos-runtime.md for namespaces and migration.

For web routes, Render:

1. normalizes route params
2. resets Meta state
3. instantiates the controller
4. executes the action unless the route uses `noAction()`
5. interprets the returned data
6. loads group meta and view meta
7. renders the target view
8. wraps it in the target template plus header and footer

## Meta Layering

Per view family:

- group meta: `<module>.group.meta.php`
- view meta: `<viewName>.meta.php`

Render applies global meta, then template meta, module extensions for that template, group meta, and view meta before injecting CSS, JS, meta tags, and schema. Footer content is resolved from PHP partials, independently of Meta.

Template meta lives in `app/views/templates/<template>.meta.php`; optional module files live in `app/views/templates/meta/<template>/*.meta.php`. This lets nested admin views load panel assets without copying them into every group meta.

## Template Rule

Templates live in `app/views/templates/`.

Current footer and header already provide:

- dynamic asset injection
- `site_url`
- token bridge
- toast mount

Footer areas are `content`, `copyright`, and `credits`. For each area, Render selects the first existing partial in this order: `<view>.footer.<area>.php` in the view folder, `<group>.footer.<area>.php` in the group folder, then `app/views/templates/footer/<area>.php`. Missing areas render nothing. The project's `footer.php` remains the outer template and owns end-of-page script loading.

Do not recreate those framework elements inside module views.

## Protected Pages

Render receives route params that include `isProtected`.

Protected state affects page-level behavior and meta handling, so preserve that signal when changing route or middleware conventions.
