# Project Structure and Meta Rules

## Relevant Folders

- `app/views/admin/<module>/` for admin views and partials
- `public/js/app/admin/<module>/` for module JS
- `public/css/app/admin/<module>/` for module CSS
- `public/js/core/` for shared JS helpers
- `public/js/core/utils/` for shared JS utilities

## CSS Layers

- framework-published tokens and components under the future `public/assets/gframe` layer
- `public/css/variables.css` and `public/css/common.css` while the legacy asset bridge remains active
- `public/css/app/common.css` for patterns shared by several application views
- `public/css/app/admin/admin.css` for application-wide admin styling
- `public/css/app/admin/<module>/<file>.css` for module-local styling

Keep one-screen styling in its module. Promote a rule to application shared CSS only when several views genuinely reuse the same component or layout.

## JS Layers

- `public/js/core/alertToast.js` for `alertToast`, `swalAlert`, and spinner helpers
- `public/js/core/utils/errors.js` for transport and business error mapping
- `public/js/core/utils/forms.js` for HTML5 validation feedback
- `public/js/app/admin/<module>/*` for module-specific behavior

## Meta File Strategy

Use meta files to load assets.

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

`app/views/templates/footer.php` already provides:

- `site_url`
- footer JS injections
- `#toastBox`
- protected-page globals such as `is_protected`

Do not duplicate those framework-level elements without a compatibility reason.
