# View, Meta, and Render Flow

## Render Role

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

Render merges both layers before injecting CSS, JS, meta tags, schema, and credits.

## Template Rule

Templates live in `app/views/templates/`.

Current footer and header already provide:

- dynamic asset injection
- `site_url`
- token bridge
- toast mount

Do not recreate those framework elements inside module views.

## Protected Pages

Render receives route params that include `isProtected`.

Protected state affects page-level behavior and meta handling, so preserve that signal when changing route or middleware conventions.
