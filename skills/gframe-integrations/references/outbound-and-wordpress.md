# Outbound HTTP and WordPress

## HttpClient

Global `HttpClient::request(array)` returns `ok`, integer HTTP `status`, `headers`, raw `body`, decoded `json` and `error`. This is not a GFrame business `status` string. Success means no cURL error and HTTP 2xx; JSON/business success must be checked separately. `json` normally contains objects from `json_decode`, or null for empty/invalid JSON. Map the remote response in the owning service.

Arrays in a non-GET/DELETE body are form encoded, even with a supplied JSON Content-Type. Encode JSON explicitly as a string or use an object when the remote API requires it. GET array body becomes query parameters; DELETE body is not sent. Header arguments are strings such as `Authorization: Bearer ...`. The client defaults to a 300-second timeout, follows redirects (maximum 10) and verifies TLS; choose bounded timeout/redirect policy for the task. There are no automatic retries/cache, and redirect response headers are not a multi-value history API. Preserve `requestCompat` only for existing consumers (`response`, `httpCode`); use `request` for new work.

Treat target URLs as backend configuration; do not make a browser-submitted arbitrary URL a privileged fetch target. On a mutating request, a timeout cannot establish whether the remote side committed the operation. Retries need the remote operation's idempotency contract, not an unconditional loop.

## WordPress Recipe

For a configured/injected `GFrame\Headless\WordPressClient`:

```php
$loaded = $wordpress->content($slug, 'post');
if (($loaded['status'] ?? '') !== 'success') {
    return $loaded;
}
return [
    'status' => 'success', 'code' => 'article_loaded',
    'data' => ['article' => $loaded['data']],
];
```

`$slug` is validated controller input; `$wordpress` uses trusted CMS base URL/token. `fromEnvironment()` reads `WORDPRESS_HEADLESS_URL` and `WORDPRESS_HEADLESS_TOKEN`. Set the HTTPS site base (including any subfolder), without `/wp-json`; never put the token in frontend configuration. The final client supports content/contentById, contents, terms, menu and schema through `/wp-json/bridgeframe/v2`. Extend through composition, not inheritance.

Requests send `X-BridgeFrame-Contract: 2.0`, with timeout 30, at most three redirects and TLS verification. Filters/options accept scalar/null values with alphanumeric/underscore keys; nested arrays/objects are dropped. Principal method arguments override duplicate options. Fields lists are comma-separated strings. `schema()` describes the remote data schema, not view JSON-LD.

The client recursively normalizes object data and requires a successful envelope with code, array data and meta.contract_version exactly 2.0. Legacy/unversioned or incompatible 2xx results become `wordpress_contract_mismatch`. Local codes replace remote success codes. Preserve meaningful errors (unauthorized/not-found/rate-limited/unavailable) rather than converting all failures into application 404s. Rate limiting does not schedule a retry or expose Retry-After through the local contract; no cache/sync is implicit.

Private=true requests private content but does not authorize the GFrame actor. Remote scopes and local user/tenant authorization are separate; select trusted per-tenant configuration explicitly. A credential may read more than the current user may see.

Render CMS HTML only under the project's trust/sanitization policy; escape separate text fields. Public/frontend instructions own view meta and SEO. WordPress styles load only on content views, base style before theme, inside `gf-wordpress-content`; they do not execute block scripts or rewrite CMS menu/media URLs. Verify any application URL mapping separately.

For offline tests inject a callable as WordPressClient's third constructor argument returning the HTTP contract; `fromEnvironment` also accepts that callable. This substitutes network behavior, not real TLS/BridgeFrame capability. Do not supply the test transport in production.
