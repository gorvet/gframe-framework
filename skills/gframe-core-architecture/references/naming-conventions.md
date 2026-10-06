# Naming and Compatibility

Use this for new GFrame-owned code. Existing public names, request keys, selectors and extension points remain contracts; changing their spelling is a separate compatibility task.

## Name by Context

| Context | Convention and example |
| --- | --- |
| PHP variables and function parameters | camelCase; use `ID` for an identifier suffix: `$userID`, `$tenantID`, `$userIDs`. |
| JavaScript variables and function parameters | camelCase. Prefer the same `ID` suffix for new GFrame-owned business identifiers; preserve external API names and existing component contracts. |
| Classes | PascalCase: `NotificationController`, `SessionManager`, `GFSelect`. |
| Methods and functions | camelCase with a meaningful action: `markRead`, `resetPassword`. |
| SQL tables and columns | snake_case: `tenant_memberships`, `user_id`. |
| New business request/JSON keys | snake_case: `user_id`, `notification_id`, `user_ids`. Preserve technical keys such as `csrfToken`, `csrfTimestamp`, `http_code` and the documented envelope keys. |
| Module names, skill folders and new URL segments | kebab-case: `user-admin`, `gframe-auth-access`. |
| New constants and environment variables | UPPER_SNAKE_CASE: `TENANT_TABLE`, `MAIL_RATE_LIMIT_ENABLED`. Preserve legacy bridge names. |
| New response codes | lowercase snake_case: `invalid_token`, `password_reset`. |
| Permission slugs | Preserve the documented area/action form, such as `users.view`; do not convert it into a response code. |

A function parameter is a code identifier, not an HTTP field name. `$userID` and the input key `user_id` may represent the same value. Hyphens are not valid parts of PHP/JS variable identifiers.

Router placeholder keys must agree with the controller's `params` lookup. A single `id` is valid; do not rename it just to match a database column. Keep existing route inference and public URLs.

HTML IDs, CSS selectors and component naming rules belong to `gframe-frontend-admin`, not to AGENTS or a duplicate architecture checklist. Preserve existing selectors and third-party attributes. For example, user-admin intentionally maps HTML `data-user-id` to request `user_id`, then to PHP `$userID` and SQL `user_id`.

## Preserve Consumers

- Before a rename, locate declarations and consumers across PHP, JavaScript, templates, routes, SQL, tests and documentation.
- A local variable rename differs from changing a public parameter: PHP named arguments and overrides can depend on parameter names.
- Preserve existing names such as `registerAcount`, module aliases such as `gfselect`, and component APIs such as `GFSelect`. A corrected public spelling needs an explicit migration or alias decision.
- Do not automatically rewrite payload keys, CSRF fields, configurable tenant keys, stored data, published URLs or selectors.
- Do not normalize response codes in Router or JavaScript. Update producers and consumers only in an explicitly approved compatibility change.
- Keep vendor code and external standards outside naming cleanup.

This reference is shared by the canonical GFrame skills. When skills are copied, keep the matching-version `gframe-core-architecture` companion. If it is unavailable, locate this reference in the project's resolved GFrame package instead of substituting a newer global convention.
