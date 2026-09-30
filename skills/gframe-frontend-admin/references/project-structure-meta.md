# Project Structure and Meta Rules

## Relevant Folders

- `app/views/admin/<module>/` for admin views and partials
- `public/js/app/admin/<module>/` for module JS
- `public/css/app/admin/<module>/` for module CSS
- `public/js/core/` for shared JS helpers
- `public/js/core/utils/` for shared JS utilities

## CSS Layers

The admin panel uses Bootstrap's `data-bs-theme` as the sole theme selector. Personalization belongs in `public/css/variables.css`, loaded after Bootstrap, and existing button/component customization files. Do not introduce a separate theme.css layer. Preserve `GFTheme` and `gf-theme` persistence. Keep color and `-rgb` variables aligned; brand variables alone do not recolor compiled buttons. Do not add a second theme controller or `data-gf-theme` selectors.

- framework-published optional components under `public/vendors/internal/` and external libraries under `public/vendors/external/`
- `public/css/variables.css` and `public/css/common.css` while the legacy asset bridge remains active
- `public/css/app/common.css` for patterns shared by several application views
- `public/css/app/admin/admin.css` for application-wide admin styling
- `public/css/app/admin/<module>/<file>.css` for module-local styling

Keep one-screen styling in its module. Promote a rule to application shared CSS only when several views genuinely reuse the same component or layout.

## JS Layers

- `public/vendors/internal/gframe-alerts/alertToast.js` for `alertToast`, `swalAlert`, and spinner helpers
- `public/js/core/utils/errors.js` for transport and business error mapping
- `public/js/core/utils/forms.js` for HTML5 validation feedback
- `public/js/app/admin/<module>/*` for module-specific behavior

## Meta File Strategy

Use meta files to load assets.

The admin template's shared assets live in `app/views/templates/admin.meta.php`. Modules may add template-level assets through `app/views/templates/meta/admin/*.meta.php`; these are loaded before group and view meta.

Group meta:

- `app/views/admin/<module>/<module>.group.meta.php`

View meta:

- `app/views/admin/<module>/<viewName>.meta.php`

Use group meta for assets reused across module views.
Use view meta for screen-specific CSS, JS, title, schema, or extra dependencies.

## Header and Footer Guarantees

`app/views/templates/header.php` already provides:

- dynamic CSS and JS from Meta
- global token form `#tokens`
- CSRF values used by AJAX flows
- `site_url` and `is_protected` globals

`app/views/templates/footer.php` already provides:

- footer JS injections
- the optional footer areas

The admin template provides `#toastBox`.

The `alerts` module also publishes its toast styles. Load both the module CSS and JS through meta files.

Do not duplicate those framework-level elements without a compatibility reason.
