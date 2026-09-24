---
name: gframe-frontend-admin
description: Implement admin frontend in GFrame PHP apps with view meta files, modular CSS and JS, jQuery AJAX form flows, HTML5 validation helpers, alertToast, swalAlert, URL-synced filters, and server-rendered HTML partial replacement.
---

# GFrame Frontend Admin

Use this when creating or modifying admin views in `app/views/admin/*` and their paired assets in `public/js/app/admin/*` and `public/css/app/admin/*`.

## Read Order

1. [references/project-structure-meta.md](references/project-structure-meta.md)
2. [references/view-form-structure.md](references/view-form-structure.md)
3. [references/ajax-feedback-pattern.md](references/ajax-feedback-pattern.md)
4. [references/list-filter-pagination.md](references/list-filter-pagination.md)
5. [references/media-components.md](references/media-components.md) only when media picker or media field is involved

## Workflow

1. Locate module and subview such as `index`, `create`, `edit`, or modal partials.
2. Register only the needed CSS and JS in group meta and view meta files.
3. Build the PHP view with Bootstrap and existing admin structure conventions.
4. Use header-provided tokens and core JS helpers for AJAX requests and feedback.
5. Keep HTML5 form validation in the view and validation orchestration in JS.
6. For list flows, replace the mount container with backend-rendered `html`.
7. Keep URL and filter state synced only when the module already follows that pattern.
8. Treat module JS as behavior orchestration, not as the source of markup or user-facing copy.

## Hard Rules

- Do not modify GFrame source or vendored files to make an application screen fit.
- If the screen depends on backend or tenancy behavior that does not match the framework, stop and surface the mismatch before proposing changes.
- Do not modify `app/views/templates/header.php` or `app/views/templates/footer.php` unless explicitly requested.
- Reuse the global `#tokens` form injected by header. Do not add a new token form unless the module already relies on a legacy local token bridge.
- Build layout first with Bootstrap `container`, `container-fluid`, `row`, and `col-*`.
- If Bootstrap columns do not solve the screen cleanly, prefer flexbox for the custom layout.
- Do not use CSS Grid by default in admin views or module CSS. Use it only as a last resort when Bootstrap columns and flexbox are clearly not enough.
- Keep module-specific CSS in `public/css/app/admin/<module>/...`; move truly reused application patterns to the application's shared CSS layer.
- Keep module-specific JS in `public/js/app/admin/<module>/...`.
- Load assets through meta files, not by hardcoding script tags in views.
- Follow backend response keys already used by the framework: `status`, `message`, `code`, `data`, `meta`, `html`.
- Treat `response.code` as an exact contract value. Compare the canonical string directly and do not apply `toLowerCase()` or other casing normalization before branching.
- Use `public/js/core/alertToast.js` for `alertToast` and `swalAlert`.
- Use `public/js/core/utils/errors.js` for `ajaxError()` and `successError()`.
- Use `public/js/core/utils/forms.js` for `validationFeedback()`.
- Do not use `window.alert`, `window.confirm`, or raw `Swal.fire` directly in normal module code.
- Use `alertToast` for normal success, warning, business error, and transport error feedback.
- Use `swalAlert` for destructive confirmations or blocking decisions that require explicit confirmation.
- `public/js/core/utils/errors.js` should only centralize shared/core code handling such as `forbidden`, `not_found`, `service_unavailable`, `to_reload`, numeric aliases, and transport errors.
- `public/js/core/alertToast.js` and `swalAlert` are presentation helpers only. They must not reinterpret or normalize backend `code` values.
- If a form uses HTML5 validation attributes such as `required`, keep the standard pattern: `class="needs-validation"` plus `novalidate`, then JS `checkValidity()` plus `validationFeedback(...)` plus `was-validated`.
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
3. Forms use framework validation helpers when HTML5 validation is present.
4. JS shows feedback through `alertToast` or `swalAlert`.
5. HTML replacement comes from backend partials when the module is list-based.
