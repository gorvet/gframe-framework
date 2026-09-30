---
name: gframe-auth-access
description: Implement and review GFrame MVC authentication, normalized sessions, standard user models, My Account management, protected system roles, and role permissions.
---

# GFrame Authentication and Access

Use this for registration, login, verification, recovery, session identity, My Account actions, and administrative hierarchy.

## Read Order

1. [references/authentication-contracts.md](references/authentication-contracts.md)
2. [references/administrative-hierarchy.md](references/administrative-hierarchy.md)

## Rules

- Keep the standard MVC flow: controller, authentication service, `UserModel` or `RoleModel`, ORM, database.
- Do not introduce repository layers around the standard GFrame models.
- Use `auth-ui`, `self-account` and `user-admin` as the reusable base MVC modules. Keep brand-specific view overrides, email copy, redirects, additional roles, profiles, areas and domain policies in the application.
- Use `AuthService` for registration, verification, recovery, reset, and credential authentication.
- Use `SessionManager` to regenerate, normalize, update, and destroy sessions.
- Use `SelfAccountService` for the current user's base account, password change, and account deactivation.
- Never expose password hashes or tokens in public profile responses.
- Do not reveal whether an email exists during password recovery.
- The first installed user receives the unique protected `superadministrator` role. Additional administrators are optional and subordinate.
- Keep the superadministrator deactivation protection inside the framework service.
- Treat normalized `auth` identity as the framework contract. Preserve legacy session keys only as a temporary application migration bridge.
- Protect routes in middleware, not inside persistence models.

## Verification

- Test registration, verification, login, recovery, reset, profile loading, password change, deactivation protection, and role hierarchy.
- Confirm the application keeps its established AJAX codes and user-facing messages.
- Avoid mutating real users during diagnostics.
