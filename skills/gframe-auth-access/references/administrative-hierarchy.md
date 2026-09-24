# Administrative Hierarchy

- Installation creates the first user as the unique superadministrator.
- Superadministrator status is separate from the ordinary role string.
- Superadministrators pass `admin` and `can:*` middleware regardless of ordinary role.
- `auth.administrator_roles` lists optional roles that pass `admin` middleware.
- An administrator never receives superadministrator identity implicitly.
- Only the superadministrator may manage administrator assignments when the application exposes that feature.
- Applications may add specialists, workers, editors, or other roles without changing the framework hierarchy.
