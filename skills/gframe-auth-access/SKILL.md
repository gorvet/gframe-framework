---
name: gframe-auth-access
description: Implement and review GFrame MVC authentication, normalized sessions, standard user models, My Account management, protected system roles, and role permissions.
---

# GFrame Authentication and Access

For new symbols or a naming review, use the matching-package [shared naming reference](../gframe-core-architecture/references/naming-conventions.md). Preserve existing APIs, keys and selectors; locate the companion in the effective package if installed separately.

Use this for registration, login, verification, recovery, session identity, My Account actions, and administrative hierarchy.

## Read Order

1. [references/authentication-contracts.md](references/authentication-contracts.md)
2. [references/administrative-hierarchy.md](references/administrative-hierarchy.md)
3. For session or tenant work, [references/sessions-and-tenancy.md](references/sessions-and-tenancy.md).
4. For an HTTP-to-service account example, [references/self-account-password-recipe.md](references/self-account-password-recipe.md).

## Rules

- Keep authentication MVC direct: `AuthController -> AuthModel -> ORM`. Account and user administration retain their existing services and standard models.
- Do not introduce repository layers around the standard GFrame models.
- Use `auth-ui`, `self-account` and `user-admin` as the reusable base MVC modules. Keep brand-specific view overrides, email copy, redirects, additional roles, profiles, areas and domain policies in the application.
- Use the MVC model `AuthModel` for registration, verification, recovery, reset, and credential authentication.
- Administrative registration notices use `auth.registration_admin_notice`, disabled by default. Keep recipient authorization global, use `email`/`inbox`, and preserve registration success if a notice fails. Mail runs through Async; verification and resend do not trigger another notice. Consult the matching package's authentication guide before enabling required transports/modules.
- Use `SessionManager` to regenerate, normalize, update, and destroy sessions.
- Use `SelfAccountService` for the current user's base account, password change, and account deactivation.
- Never expose password hashes or tokens in public profile responses.
- Email fields use native type=email/required without restrictive patterns that reject + aliases or domain suffixes longer than three characters. Preserve server FILTER_VALIDATE_EMAIL checks and test login, registration and recovery with valid aliases and malformed addresses. Project view overrides may retain obsolete patterns after updating the package; review them explicitly. Format validation does not prove mailbox deliverability.
- Preserve password bytes through the HTTP boundary. Do not trim, HTML-escape, lowercase or apply display-text sanitization to passwords; keep field names and PasswordPolicy checks unchanged.
- Do not reveal whether an email exists during password recovery.
- The first installed user receives the unique protected `superadministrator` role. Additional administrators are optional and subordinate.
- Keep the superadministrator deactivation protection inside the framework service.
- Treat normalized `auth` identity as the framework contract. Preserve legacy session keys only as a temporary application migration bridge.
- Password reset and changes revoke sessions through an available managed registry; native PHP/file sessions are rejected. Verify the driver and registry instead of promising the same behavior for all storage modes.
- Keep tenant selection consistent with route/request/session identity, then verify membership and permissions. Registration does not create a tenant or membership.
- Protect routes in middleware, not inside persistence models.
- Private media visibility does not replace owner/tenant or record authorization. Native media adds source-configurable private action permissions to its ordinary route permissions; other administrator roles need explicit grants. External download tokens are separate capabilities and must not imply admin access. PrivateDownloadService requires a controller-verified user ID for identity restrictions and an optional current-entitlement callback; it never reads or validates a session itself. Follow docs/archivos-privados.md in the resolved package, without introducing business-specific roles or retention rules.

## Verification

- Test the affected authentication/account flows; a change spanning them requires registration, verification, login, recovery, reset, profile, password change, deactivation and role-hierarchy coverage. A recipe-only edit requires source contrast, relevant existing checks and skill/link validation, not unrelated account mutations.
- Confirm the application keeps its established AJAX codes and user-facing messages.
- Avoid mutating real users during diagnostics.
