# Authentication MVC

## Framework Services

- `GFrame\Auth\AuthService`
- `GFrame\Auth\AuthInstallationService`
- `GFrame\Auth\SelfAccountService`
- `GFrame\Auth\SessionManager`
- `GFrame\Auth\PasswordPolicy`
- `GFrame\Auth\TokenManager`
- `GFrame\Auth\RolePermissionService`

## Standard Models

Use `UserModel` and `RoleModel` with the standard schema. Controllers call services for authentication or authorization rules, and services call these ORM models. Do not add a repository layer around them. If an application authenticates against an external API or identity server, implement a dedicated integration at the application boundary rather than complicating the standard path.

Keep project-specific profile data outside the framework `users` table. The base My Account flow works through `UserModel`; applications add their own related model for names, phones, avatars, or domain-specific profile fields.

Controllers remain the HTTP boundary and add project messages, email templates, redirects, area assignments, and legacy session keys.

The normalized `$_SESSION['auth']` identity is the framework source of truth and contains `id`, `email`, `name`, `role_id`, and `role`. `name` may come from an application profile and is not required in the framework users table. Do not add a duplicate superadministrator boolean flag.
