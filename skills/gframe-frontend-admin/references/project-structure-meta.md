# Project Structure and Meta Rules

## Relevant Folders

- `resources/modules/<module>/application/app/` for framework-owned runtime originals, when declared by the manifest
- `app/views/<module>/` for project overrides of those runtime views; resolve originals and overrides through `ModuleRuntime::file`
- `app/views/admin/<module>/` for application-owned or legacy admin views that already use that structure
- module manifest asset sources for framework-owned JS/CSS; published targets are relative to the project's `public/`, for example `js/modules/user-admin`
- `public/js/app/admin/<module>/` and `public/css/app/admin/<module>/` for application assets following that convention
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

These application layers do not replace a distributable module's manifest targets. For example, user-admin edits `resources/modules/user-admin/javascript/user-admin.js` and publishes it as `public/js/modules/user-admin/user-admin.js`. Do not edit only a generated asset and expect the change to survive an update.

## Meta File Strategy

The rich-text-editor component also uses runtime originals: resolve rich-text-editor/richTextEditor.php and richTextEditor.meta.php through ModuleRuntime::file('views', ..., 'rich-text-editor'). Project overrides live in app/views/rich-text-editor/. Do not depend on copied originals under app/views/admin/components; its public JS destination remains unchanged. Sanitize untrusted saved rich HTML in backend through GFrame\Security\HtmlSanitizer, not only TinyMCE paste cleanup.

The distributable admin-panel now keeps its original controller, dashboard, template, meta and parts under resources/modules/admin-panel/application/app/. Project customizations use app/controllers/admin-panel/ and app/views/admin-panel/; parts use app/views/admin-panel/parts/. Shared admin templates and meta resolve project first and the runtime template provider second. Do not require copied admin originals in app. Module menu/header contributions remain published under admin-panel/parts; use ModuleRuntime::file for original/custom panel partials. Old app/views/admin personalizations require explicit migration, not automatic deletion.

Use meta files to load assets.

The admin template's shared assets resolve the project override `app/views/templates/admin.meta.php` first, then the admin-panel runtime provider. Modules may add template-level assets through `app/views/templates/meta/admin/*.meta.php`; these are loaded before group and view meta.

Group meta:

- runtime original: `resources/modules/<module>/application/app/views/<module>/<module>.group.meta.php`
- project override: `app/views/<module>/<module>.group.meta.php`
- application-owned/legacy view: `app/views/admin/<module>/<module>.group.meta.php` when that is its existing group path

View meta:

- the corresponding resolved view directory and `<viewName>.meta.php`; do not force a runtime module into an `admin/` directory

Use group meta for assets reused across module views.
Use view meta for screen-specific CSS, JS, title, schema, or extra dependencies.

## Header and Footer Guarantees

`app/views/templates/header.php` already provides:

- dynamic CSS and JS from Meta
- global token form `#tokens`
- CSRF values used by AJAX flows

`app/views/templates/footer.php` already provides:

- footer JS injections
- the optional footer areas
- `site_url` and `is_protected` globals before the module's footer JS

The admin template provides `#toastBox`.

The `alerts` module also publishes its toast styles. Load both the module CSS and JS through meta files.

Do not duplicate those framework-level elements without a compatibility reason.

These guarantees describe the standard skeleton template. Check customized templates and the module's existing bridge before assuming a token form or helper is available. User-admin currently serializes `#user-admin-tokens`; preserve it when changing that module rather than silently switching selectors.
