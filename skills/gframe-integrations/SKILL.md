---
name: gframe-integrations
description: Implement and review GFrame outbound HTTP and WordPress BridgeFrame clients or inbound API, webhook and SSE channels, preserving credentials, envelopes, scopes and tenant authorization.
---

# GFrame Integrations

For new symbols or a naming review, use the matching-package [shared naming reference](../gframe-core-architecture/references/naming-conventions.md). Preserve existing APIs, keys and selectors; locate the companion in the effective package if installed separately.

Choose the transport actually requested. Read [outbound HTTP and WordPress](references/outbound-and-wordpress.md) for remote consumption; read [inbound API, webhook and SSE](references/inbound-channels.md) for exposing channels. An outbound credential is not an inbound API consumer, and CORS is not authentication.

Resolve the loaded package/version and full guides there: `docs/http-client.md`, `docs/wordpress-headless.md`, `docs/api-access.md`, `docs/rutas.md`. The local WordPress client declares BridgeFrame contract 2.0; this is not a plugin release number. Verify remote capability before relying on fields not supported by this client.

Use project services/controllers and supported provider injection for application integrations. Change standalone framework originals only within a requested framework task. Keep public transport contracts and backend/frontend error rendering; do not add a global normalization layer or use remote error details as UI copy.

Resolve trusted URLs, actor, scopes and tenant in the backend; supplied IDs/private flags do not authorize access. Keep credentials in environment/provider configuration and preserve TLS verification. Do not expose tokens through public JS, rendered data, URLs or logs. A route/channel guard does not filter domain queries or supply business idempotency.

Use injected WordPress transports, local bounded HTTP fixtures and fake credential providers for diagnostics. Existing tests include `WordPressHeadlessTest`, `WordPressDocumentationTest`, `ApiBearerCorsTest`, `MiddlewareDocumentationTest` and `AuditRegressionTest`. Verify unauthorized/malformed responses, case-sensitive secrets, scopes and tenant isolation. Record real TLS, remote plugin, proxy buffering and browser EventSource checks separately. Testing these instructions does not authorize contacting production endpoints or registering remote subscriptions.
