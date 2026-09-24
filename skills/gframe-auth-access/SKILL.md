---
name: gframe-auth-access
description: Implement and review GFrame authentication, normalized sessions, self-account management, superadministrator hierarchy, configurable administrator roles, and application repository adapters without imposing a project schema.
---

# GFrame Authentication and Access

Use this for registration, login, verification, recovery, session identity, self-account actions, and administrative hierarchy.

## Read Order

1. [references/authentication-contracts.md](references/authentication-contracts.md)
2. [references/administrative-hierarchy.md](references/administrative-hierarchy.md)

## Rules

- GFrame services depend on contracts; the application adapts its user table through repositories.
- Keep views, email copy, redirects, initial application roles, areas, and domain policies in the application.
- Use `AuthService` for registration, verification, recovery, reset, and credential authentication.
- Use `SessionManager` to regenerate, normalize, update, and destroy sessions.
- Use `SelfAccountService` for the current user's profile, password change, and account deactivation.
- Never expose password hashes or tokens in public profile responses.
- Do not reveal whether an email exists during password recovery.
- The first installed user is the unique superadministrator. Additional administrators are optional and subordinate.
- Require an `AccountDeactivationPolicy` so the superadministrator cannot deactivate their own account.
- Preserve legacy session keys only as an application compatibility bridge; also populate normalized `auth` identity.
- Protect routes in middleware, not inside persistence models.

## Verification

- Test registration, verification, login, recovery, reset, profile loading, password change, deactivation protection, and role hierarchy.
- Confirm the application keeps its established AJAX codes and user-facing messages.
- Avoid mutating real users during diagnostics.
