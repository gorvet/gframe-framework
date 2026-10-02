# AJAX and Feedback Pattern

## Preferred Request Style

Use jQuery AJAX patterns already common in GFrame:

- `$.ajax({...}).done(...).fail(...)` for lists and actions
- `$.post(...)` only for very short, simple flows

Always include CSRF data when the route requires it.
The normal source is the global `#tokens` form.

## Payload and Response Contract

Expected backend JSON keys:

- `status`
- `message`
- `code`
- `data`
- `meta`
- `html`

Use only the keys that the endpoint needs, but do not invent replacements casually.

## Feedback Helpers

Use framework helpers from shared JS:

- `alertToast({ icon, title })`
- `alertToast` accepts `timer` in milliseconds (default 5000, zero persists); the progress bar matches that duration. Titles are plain text, never raw HTML. The toast uses Bootstrap body variables for both themes. See `docs/alerts.md`.
- `swalAlert(options)`
- Preserve the existing SweetAlert2 `sweetTheme.css` bridge instead of replacing the external library. `swalAlert` keeps caller options immutable, retains Bootstrap classes when customClass is partial, and defaults to Cancel before the main action with centered buttons. Normal form actions remain right-aligned. Keep the loading popup's action container visible because it contains the loader.
- `successError(message, code)`
- `ajaxError(status, error)`
- `showSpinner(...)`

## When To Use Each Helper

Use `alertToast` for:

- success notifications after save, delete, update, reload
- business errors returned by backend
- transport or parser errors after mapping through `ajaxError`
- non-blocking warnings

Use `swalAlert` for:

- destructive confirmations
- flows that require explicit user confirmation
- blocking informational dialogs where a modal is intentional

Do not use `swalAlert` as the default replacement for every toast.

## Recommended Success Flow

1. Build request data from `#tokens` plus form payload.
2. Send AJAX.
3. In success:
   - if `status === 'success'`, update UI and show success toast if appropriate
   - else map backend error with `successError(message, code)` before notifying
4. In fail:
   - map transport error with `ajaxError(status, error)`
   - notify with `alertToast`

## HTML Replacement Rule

If the endpoint returns `html`, replace the target mount with that markup instead of generating the block in JS.
