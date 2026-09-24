# Authentication Contracts

## Framework Services

- `GFrame\Auth\AuthService`
- `GFrame\Auth\SelfAccountService`
- `GFrame\Auth\SessionManager`
- `GFrame\Auth\PasswordPolicy`
- `GFrame\Auth\TokenManager`

## Application Adapters

Implement `AuthUserRepository` for credential and token persistence. Implement `SelfAccountRepository` for profile and account persistence. Implement `AccountDeactivationPolicy` for protected identities.

Controllers remain the HTTP boundary and add project messages, email templates, redirects, area assignments, and legacy session keys.

The normalized session identity contains `id`, `email`, `name`, `role`, and `is_super_admin`.
