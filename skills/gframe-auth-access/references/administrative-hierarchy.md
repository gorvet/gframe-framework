# Administrative Hierarchy

- Installation creates the first user as the unique superadministrator.
- The first user receives the protected system role `superadministrator`; no duplicate boolean flag is used by the normalized schema.
- Superadministrators pass `admin` and `can:*` middleware regardless of ordinary role.
- Other roles pass `admin` only through the `admin.access` permission.
- An administrator never receives superadministrator identity implicitly.
- Only the superadministrator may manage administrator assignments when the application exposes that feature.
- Applications may add specialists, workers, editors, or other roles without changing the framework hierarchy.
- The superadministrator role cannot be reassigned, removed, renamed, or deleted through ordinary role management.
- Middleware resolves the stored role for authorization and does not trust a session role slug as sufficient authority.
