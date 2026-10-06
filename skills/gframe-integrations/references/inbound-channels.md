# Inbound API, Webhook and SSE

## Routing and API

Declare routes in the appropriate `config/routes/routes_*.php` file and include the URL prefix explicitly. Declared type and executed URL channel are distinct; a routes filename does not add `api/`, `webhook/` or `sse/`. Preserve Router/Middleware channel handling rather than creating another dispatcher.

API Bearer credentials are case-sensitive. Configure a server-owned route token/consumer or `api_consumers`, or inject `GFrame\Http\Contracts\ApiCredentialProvider` into the middleware actually used by bootstrap. Instantiating a new middleware inside the action does not replace the one that guarded the request. The provider authenticates consumer/name/tenant/scopes/origins and must not return the secret.

OPTIONS validates origin without Bearer and returns 204. Real requests still authenticate; server-to-server clients may omit Origin. CORS only controls browser access. After authentication, Router places `api_consumer`, `api_tenant_id`, `api_scopes` into execution context. The action checks required scopes explicitly, for example:

```php
$context = $routeParams['context'] ?? [];
if (!in_array('orders.read', (array)($context['api_scopes'] ?? []), true)) {
    return ['status' => 'unauthorized', 'code' => 'scope_required', 'http_code' => 403];
}
$tenantID = $context['api_tenant_id'] ?? null;
```

`$routeParams` is the action's Router execution parameter. Validate the resulting tenant for the domain and pass it to filtered queries; never replace it with a request tenant or treat null as a safe fallback for a tenant-only resource. This snippet checks scope only, not membership or resource access.

## Webhooks

Native webhook guard blocks OPTIONS/Origin/Referer, enforces allowed methods (default POST) and rejects declared Content-Length above 2 MiB. This is not streamed body-size enforcement. Without a verifiable custom credential it requires JSON and `X-Webhook-Secret` matching configured `WEBHOOK_DEFAULT_SECRET`; merely requiring a header/parameter is not secret verification.

For native HMAC configure `require_header`, `validate_hmac` and nonempty `hmac_secret`. Signature format is exactly `sha256=` plus SHA-256 HMAC over raw body; the guard caches it in `$GLOBALS['RAW_INPUT']`. Malformed/incomplete HMAC configuration rejects access. This signature format must match the provider; other formats need a scoped provider-specific adapter, not weakening verification. Do not combine `expected_value` for the same signature header accidentally.

No automatic timestamp/replay ledger or event deduplication is provided by the guard. Verify provider event identity/state and idempotency in the service before business writes. Do not assume absence of Origin authenticates a request. Keep expected failures controlled; raw transport errors do not belong in public responses.

## SSE

SSE guard requires GET and does not support OPTIONS. Keep declared auth when using session streams; same origin alone is not resource authorization. With `require_token`, configure nonempty `expected` or callable `verify`; the presence of any token is insufficient. If both are configured both must pass. Current resolution prefers the named query token then the configured header/Bearer. Avoid long-lived URL secrets; if the browser requires query credentials, use the project's explicit scoped short-lived token design.

Router supplies event-stream headers and attempts to disable PHP buffering; the session lock is released before the action. Stream handlers must own event framing, disconnects, resource limits and any resume/history policy. The guard does not provide event storage, delivery acknowledgement or replay. Long-lived streams do not automatically re-run authorization as account state changes; define that behavior when the feature needs it. Verify proxy/browser buffering and reconnection in deployment before claiming a working realtime flow.
