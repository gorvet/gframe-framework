# Heartbeat Channels

Heartbeat is session/browser polling, not persistent execution. The `heartbeat-client` manifest supplies native controller, published scripts and protected `ajax/heartbeat` route. Extend the runtime controller in `app/controllers/heartbeat-client/HeartbeatController.php`, namespace `App\Controllers\HeartbeatClient`, and call `parent::__construct()` before adding channels. Preserve native session/notification channels.

Inside that constructor, a read-only project summary can register:

```php
$this->registerChannel('project.summary', [
    'interval_ms' => 120000,
    'run_when_hidden' => false,
    'payload' => ['limit' => 20],
], static function (array $payload, array $context): array {
    $userID = (int)($context['session']['auth']['id'] ?? 0);
    if ($userID <= 0) {
        return ['status' => 'unauthorized', 'code' => 'login_required'];
    }
    return ['status' => 'success', 'code' => 'summary_updated', 'data' => []];
});
```

Replace the empty summary with the owning authorized read service when requested. Payload is backend-defined; context includes session plus client-controlled visible/force flags. A controller-reference handler is instantiated without arguments. Channel keys are lowercase letters/numbers with `._-`, up to 80 characters; registering the same name replaces it. Intervals clamp to at least 60000 ms. Force bypasses interval waiting but not the hidden-tab restriction.

Keep route auth and `noRefreshSession()`: polling must not extend idle lifetime. The infrastructure route excludes CSRF; mutations use their own protected routes. Each service must still authorize user/tenant access. Revocation happens when account state changes, not by querying users each tick. Channels should read summaries; do not run destructive account lifecycle or deliveries from a custom polling callback.

Inspect `data.channels` individually, even when the outer response is successful. The dispatcher catches `Exception` per handler and includes its message, so return controlled errors and log internal details in the owning layer. Follow backend contracts for expected failures; do not add a mandatory `message` to every successful result.

Client event `gf:heartbeat:project.summary` exposes the channel response in `event.detail.payload`. Preserve BroadcastChannel/localStorage coordination and the application base-URL boundary. Static/PHP tests do not establish leader election, hidden-tab timing, focus or logout behavior in a real browser.
