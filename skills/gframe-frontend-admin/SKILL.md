---
name: gframe-frontend-admin
description: Implement admin frontend in GFrame PHP apps with view meta files, modular CSS and JS, jQuery AJAX form flows, HTML5 validation helpers, alertToast, swalAlert, URL-synced filters, and server-rendered HTML partial replacement.
---

# GFrame Frontend Admin

For new symbols or a naming review, use the matching-package [shared naming reference](../gframe-core-architecture/references/naming-conventions.md). Preserve existing APIs, keys and selectors; locate the companion in the effective package if installed separately.

Use this when creating or modifying GFrame admin views, application overrides, or distributable module sources under `resources/modules/*`. Determine ownership and asset destinations from the module manifest before choosing a path; `app/views/admin/*` is not the universal location. Follow the approved layout and acceptance checks in the references below; do not rely on conversation memory.

## Read Order

1. [references/project-structure-meta.md](references/project-structure-meta.md)
2. [references/view-form-structure.md](references/view-form-structure.md)
3. [references/ajax-feedback-pattern.md](references/ajax-feedback-pattern.md)
4. [references/list-filter-pagination.md](references/list-filter-pagination.md)
5. [references/media-components.md](references/media-components.md) only when media picker or media field is involved
6. [references/user-admin-ajax-recipe.md](references/user-admin-ajax-recipe.md) for a concrete runtime view, field mapping and AJAX list/action flow
7. For rich-text editor lifecycle, Markdown or lexical search, [the content integration recipe](../gframe-backend/references/content-editor-search.md); locate the companion in the effective package if installed separately.
8. For shared utilities or select/table/vendor integration, [component contracts and readiness](references/component-integration.md); load only the component guide affected by the task.

## Workflow

1. Locate the module manifest, runtime originals, project overrides and subview such as `index`, `create`, `edit`, or modal partials. Edit the source that belongs to the authorized framework or application task.
2. Register only the needed CSS and JS in group meta and view meta files.
3. Build the PHP view with Bootstrap and existing admin structure conventions.
4. Use header-provided tokens and core JS helpers for AJAX requests and feedback; preserve an existing module-local token bridge when that is its current contract.
5. Keep HTML5 form validation in the view and validation orchestration in JS.
6. For list flows, replace the mount container with backend-rendered `html`.
7. Keep URL and filter state synced only when the module already follows that pattern.
8. Treat module JS as behavior orchestration, not as the source of markup or user-facing copy.

## Hard Rules

- For an application screen, do not modify GFrame source or vendored files to make it fit. For an authorized framework module change, edit its distributable originals rather than an installed application copy.
- If the screen depends on backend or tenancy behavior that does not match the framework, stop and surface the mismatch before proposing changes.
- Do not modify `app/views/templates/header.php` or `app/views/templates/footer.php` unless explicitly requested.
- Reuse the global `#tokens` form injected by header. Do not add a new token form unless the module already relies on a legacy local token bridge.
- Build layout first with Bootstrap `container`, `container-fluid`, `row`, and `col-*`.
- If Bootstrap columns do not solve the screen cleanly, prefer flexbox for the custom layout.
- Do not use CSS Grid by default in admin views or module CSS. Use it only as a last resort when Bootstrap columns and flexbox are clearly not enough.
- For application-owned screens, keep module-specific CSS and JS in the project's established module directories; `public/css/app/admin/<module>/...` and `public/js/app/admin/<module>/...` are application conventions, not universal runtime destinations.
- For distributable modules, edit the manifest's asset sources and preserve its published targets, such as `public/js/modules/user-admin/`. Move truly reused application patterns to the application's shared CSS layer.
- Load assets through meta files, not by hardcoding script tags in views.
- Follow backend response keys already used by the framework: `status`, `message`, `code`, `data`, `meta`, `html`.
- Treat `response.code` as an exact contract value. Compare the canonical string directly and do not apply `toLowerCase()` or other casing normalization before branching.
- Use the `alerts` module for `alertToast` and `swalAlert`. Its published compatibility path is `public/vendors/internal/gframe-alerts/alertToast.js`.
- Use `public/js/core/utils/errors.js` for `ajaxError()` and `successError()`.
- Use `public/js/core/utils/forms.js` for `validationFeedback()`.
- Do not use `window.alert`, `window.confirm`, or raw `Swal.fire` directly in normal module code.
- Use `alertToast` for normal success, warning, business error, and transport error feedback.
- Use `swalAlert` for destructive confirmations or blocking decisions that require explicit confirmation.
- `public/js/core/utils/errors.js` should only centralize shared/core code handling such as `forbidden`, `not_found`, `service_unavailable`, `to_reload`, numeric aliases, and transport errors.
- `alertToast` and `swalAlert` are presentation helpers only. They must not reinterpret or normalize backend `code` values.
- For new HTML5 forms, keep the standard pattern: `class="needs-validation"` plus `novalidate`, then JS `checkValidity()` plus `was-validated`; use `validationFeedback(...)` when mapping per-field messages to its feedback targets. Preserve existing native-validity flows rather than claiming every current form calls that helper.
- Do not hardcode HTML fragments in module JS. Keep markup in PHP views or backend-rendered partials, and let JS only inject or toggle existing DOM.
- Do not hardcode user-facing copy in module JS. Prefer backend `message`, rendered PHP, existing DOM text, or server-provided payload data.
- The only acceptable JS text literals are small local UI feedback strings for `alertToast` or `swalAlert` when the backend does not already provide the message and the module truly needs immediate feedback.
- Do not move server-rendered list or fragment markup into JS string templates when the module already uses backend partials.
- Do not add `border-0` or `shadow-sm` to cards by default.

## Expected File Set

Typical admin frontend work touches some or all of:

- view PHP files
- group meta and view meta files
- module JS
- module CSS
- backend partials already used as AJAX response HTML

## Final Checks

1. Assets are registered in meta files, not hardcoded in the view.
2. AJAX sends tokens and receives JSON with stable keys.
3. HTML5 forms check validity before sending; forms with mapped per-field messages use the framework validation helper and matching feedback targets.
4. JS shows feedback through `alertToast` or `swalAlert`.
5. HTML replacement comes from backend partials when the module is list-based.
