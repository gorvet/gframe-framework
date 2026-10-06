# User Administration: Trace Before Editing

This recipe describes the current user-admin module. It is a source-reading example, not a mandate to rename another module's fields or copy its local token bridge. Locate these files in the project's resolved GFrame package when the skill is installed separately.

## Locate the Source and Destination

Start with `resources/modules/user-admin/module.php`. Its runtime root is `application/app`, and its JavaScript asset source is `javascript`, published to `public/js/modules/user-admin/`.

| Concern | Framework source | Application destination or override |
| --- | --- | --- |
| Controller | `resources/modules/user-admin/application/app/controllers/user-admin/UserAdminController.php` | `app/controllers/user-admin/` for project customization |
| Views and group meta | `resources/modules/user-admin/application/app/views/user-admin/` | `app/views/user-admin/` overrides; originals stay in the package |
| JavaScript | `resources/modules/user-admin/javascript/user-admin.js` | `public/js/modules/user-admin/user-admin.js` |
| Web route | `resources/modules/user-admin/application/routes/routes_admin_users.php` | `config/routes/routes_admin_users.php` |
| AJAX routes | `resources/modules/user-admin/application/routes/routes_ajax_admin_users.php` | `config/routes/routes_ajax_admin_users.php` |

The group meta loads the published JS and the alerts CSS/JS. Runtime view resolution uses `ModuleRuntime::file`; both the initial view and the AJAX controller resolve `user-admin/_userList.php`, so a project override must affect both.

## Follow One Role Change

1. `_userList.php` renders a row with `data-user-id` from `user_id`. Its `.js-user-actions` button carries `data-role-id` and opens `#userModal`.
2. The script obtains the row with `closest('tr')` and fills `#managedUserRole`. Its HTML field has `name="role_id"`; `#userRoleForm` uses HTML5 validation and `was-validated` before submitting.
3. The request serializes the existing `#user-admin-tokens` form and adds `user_id` from `row.data('user-id')`, `operation: 'role'`, and `role_id` from the select.
4. `POST ajax/admin/users/update` requires `auth` and `can:users.manage`. The controller reads `$_POST['user_id']` into `$userID`, derives `$actorID` from the session and calls `assignRole` with the submitted `role_id`. The service enforces account and role protections; hiding a button is not authorization.
5. The response uses `status`, `code` and an available `message`. The script displays `alertToast`; success closes the modal and reloads the current list page. Failed requests release the saving flag and re-enable controls.

This chain intentionally uses `data-user-id`, `user_id` and `$userID` for the same identity. Keep each spelling at its existing boundary.

## Follow a List Refresh

`#userFilters` submits `search`, `role` and `status`. The script adds the token fields and `page`, then posts to `ajax/admin/users/list`, protected by `auth` and `can:users.view`. The controller renders `_userList.php` into `html`; the script replaces `#userListMount` rather than assembling rows in JS.

The returned `meta.page` sets the current page. Filter changes return to page 1; successful refreshes update the URL, omitting empty filters and page 1. Delegated mount handlers continue working after replacement. Pending list requests are aborted and a revision check rejects stale responses; `aria-busy` is cleared by the current request. Business errors and transport errors show feedback, while intentional aborts do not show an error toast.

## Checks for a Change to This Flow

- Trace changed fields through PHP markup, JS payload and controller/service input, including CSRF and permission checks.
- Check initial and AJAX rendering with the same custom partial. Preserve escaping, self-protection and protected accounts.
- Check invalid form submission, action failure, duplicate submission prevention, cancellation of confirmations and a successful refresh.
- Check filters, empty and multiple-page lists, stale requests, URL state and delegated actions after replacement.
- In this repository, existing `UserAdminUiTest`, `UserAdministrationServiceTest`, and the user-admin cases in `ModuleCatalogTest` and `ModuleRuntimeTest` cover parts of that contract. Static assertions do not prove browser behavior; report browser checks separately.

For a new form that uses per-field validation messages, follow the shared `validationFeedback` pattern. This module's current role form uses native validity and Bootstrap feedback; do not claim it already calls that helper.
