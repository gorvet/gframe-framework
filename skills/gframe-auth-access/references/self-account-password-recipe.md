# Self-Account Password Change

Use this as a trace of the existing self-account contract, not as permission to change credentials, rename fields or introduce a new auth layer. Locate the sources in the project's effective GFrame package.

## Locate the Flow

- Manifest: `resources/modules/self-account/module.php`.
- Route source: `application/routes/routes_ajax_account.php`, published to `config/routes/routes_ajax_account.php`.
- Native controller: `application/app/controllers/self-account/SelfAccountController.php`; project override: `app/controllers/self-account/SelfAccountController.php`.
- Native view/group meta: `application/app/views/self-account/`; project overrides: `app/views/self-account/`.
- Script source: `javascript/self-account.js`, published to `public/js/modules/self-account/self-account.js`; CSS publishes to `public/css/modules/self-account/`.
- Service/model: `src/GFrame/Auth/SelfAccountService.php` and `UserModel.php`. Password acceptance comes from `resources/modules/password-utils/src/PasswordPolicy.php`.

Paths under `application/` and `javascript/` above are relative to the self-account module. MVC originals remain in the package; routes/assets are published. A customized controller extends the native class and injects its supported dependencies through `parent::__construct`; do not assume that declaring a subclass replaces an explicit construction elsewhere.

## Request and Identity

`POST ajax/account/password` declares `module('self-account')` and `auth`. Its AJAX filename and URL prefix agree. Router adds the normal AJAX middleware, including CSRF, unless explicitly excluded; preserve that protection.

The view supplies `#account-password-form` with `current_password`, `new_password` and `new_password_confirmation`. The script checks native form validity, disables the submit button, serializes the form plus the existing `#self-account-tokens` bridge, and requests JSON. Keep `csrfToken`/`csrfTimestamp` and the module's selectors; do not substitute a new token form merely to standardize names.

The controller obtains `$userID` from session `auth.id`, with legacy `userID` fallback. It does not accept a browser-supplied target user for this operation. Each password field is cast to a string and passed unchanged to `SelfAccountService::changePassword($userID, $currentPassword, $newPassword, $confirmation)`. Do not trim, lowercase, HTML-escape or strip characters from those values.

## Service, Persistence and Revocation

The service rejects an invalid identity or empty inputs, applies `PasswordPolicy`, compares confirmation with `hash_equals`, loads the account and verifies the current password hash. It then hashes the new password and calls the repository's `updateAccountPassword`; the standard repository is `UserModel`. Retain that existing extension contract rather than adding a wrapper layer around every model.

The default password policy accepts 8–72 UTF-8 bytes by `strlen`, not a strength score or a Unicode character count. HTML minlength/maxlength is client feedback, not equivalent server validation for multibyte input. Preserve the backend policy and test multibyte boundaries when changing it.

When an `ActiveSessionRegistry` is available, the service calls `revokeUser($userID)` after the write. Its constructor receives an injected registry or captures `SessionRuntime::registry()` at construction; initialize managed session runtime before construction or inject the registry. Do not promise multidevice revocation for the native driver or an unavailable registry. See the [session guarantees](sessions-and-tenancy.md).

The current controller removes `must_change_password` on success; it does not explicitly call logout or reauthenticate here. The service revokes managed sessions without exempting the current one, and the next managed operation enforces that state. The successful AJAX response is not a guarantee that the current session remains valid.

The password write and registry revocation are sequential, not a distributed transaction. A handled repository/registry exception produces a stable failure response; do not infer from that response alone that the password write rolled back. Do not add a transaction or silently change this behavior as part of using the recipe.

## Response and Consumer

The service's successful result is `status: success`, `code: password_updated`, without a mandatory `message`/`data`/`meta`. Its failures use established codes such as `empty_field`, `invalid_password`, `password_mismatch`, `not_found`, `invalid_current_password` and `password_update_failed`.

`SelfAccountController::withMessage` supplies a known public message only when one is absent. Router serializes the array. The current JS tests `status`, shows `alertToast`, resets the form after success and restores the button in `always`; transport failure also shows feedback. Preserve exact response codes and any already supplied message, rather than forcing six keys into every operation.

The disabled button is UI feedback, not a server-side idempotency guarantee. The recipe does not claim complete duplicate-submit protection, browser keyboard behavior or visual validation coverage.

## Verification

Existing `SelfAccountServiceTest` and `SelfAccountUiTest` cover parts of password/account behavior and source/UI contracts; `DatabaseSessionHandlerTest` covers managed session enforcement. They do not collectively prove a full browser request or Redis integration. Use a fake account repository and registry, or an isolated test database, for diagnostics; never change a real user's password to verify this recipe.

For a changed HTTP flow, verify wrong current password, mismatched/invalid new password, missing identity, success, exact response shape, revocation of the intended user only, transport failure and subsequent session behavior. Check the rendered form and CSRF/permissions separately when affected. Name unperformed browser or driver checks explicitly.
