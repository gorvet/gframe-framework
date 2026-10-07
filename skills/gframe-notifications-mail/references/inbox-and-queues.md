# Inbox and Notification Delivery

## Inbox Ownership and Runtime

The `notifications` module manifest declares its runtime originals and published routes/assets. Inspect `resources/modules/notifications/module.php` in the effective package; use project overrides at the matching runtime paths rather than copying native controllers automatically. `NotificationService` requires a `NotificationRepository`; the standard implementation is `NotificationModel`.

For an already authorized recipient and tenant, the service call is:

```php
$result = $notifications->notify($userID, [
    'title' => 'Archivo disponible',
    'message' => 'El archivo solicitado está listo.',
    'importance' => 'info',
    'action_url' => 'account',
], $tenantID);
```

`$notifications` is an injected NotificationService. `$userID` and nullable `$tenantID` come from verified server context, not arbitrary request IDs. `notify` validates required content and normalizes fields; it does not verify the recipient's tenant membership. Resolve that domain rule before calling it. A null tenant represents the global context and must not be silently used to recover from a missing tenant.

Inbox queries and mutations use the intended user/tenant context. Preserve expiration, logical deletion and unread counting across the full live scope. The module's protected POST detail route renders the modal and marks read with CSRF; the GET detail alternative does not change read state. Do not turn a display-only GET into a mutation when adding a link.

Creating a notice does not send email. User-visible links follow the existing safe-URL contract; avoid inventing client-provided paths or weakening user/tenant filters.

## Persisted Delivery Queue

`NotificationQueueService` requires an existing `NotificationQueueRepository` and `NotificationTransport`. For an email job, construct those collaborators according to the module configuration, then enqueue:

```php
$result = $queue->enqueue('email', $recipient, [
    'subject' => 'Archivo disponible',
    'template' => 'notification',
    'variables' => ['title' => 'Archivo disponible', 'message' => 'El archivo solicitado está listo.'],
], $tenantID);
```

`$queue` is the injected queue service; `$recipient` and `$tenantID` are authorized and validated before enqueueing. Enqueue validates nonempty channel/recipient, not email format or tenant membership. `notification_queued` includes `data.notification_id` and confirms persistence only. Choose the email-specific processor for mixed-channel queues; the generic service's batch method does not filter reservations by channel automatically.

`notifications-email` depends on notifications/cron-runner and publishes templates and `config/cron/register-email-queue.php`; it is an adapter to core Mail, not a second SMTP implementation. `EmailNotificationTransport` uses synchronous `MailSender::sendTemplate`, converts a Mail failure into an exception and permits only absolute HTTP(S) action URLs for the email CTA. Construct any full URL from trusted project context, not an inbox-only relative path.

`EmailQueueProcessor` reserves only email records. It reports processed/sent/retried/failed counts and uses the current `notifications.email.max_attempts` and `retry_delay_seconds` policy; attempts affect the scheduled delay. A successful batch envelope can contain failed individual deliveries: inspect its counters rather than reporting that every job succeeded. The generic queue service and inbox processor have different failure behavior; do not claim the email retry policy applies universally.

The standard queue recovers expired processing reservations before reserving, within the requested channel. It reuses available_at as expiry while processing and increments attempts as a fencing generation; no schema migration is needed. Native processors renew before sending and complete only the active, unexpired generation, reporting lost leases in data.lost. Configure notifications.queue.lease_seconds (default 900, minimum 60) longer than the transport's bounded send duration, or renew within a long transport. LeasedNotificationQueueRepository is an optional extension; legacy repositories retain their API but do not gain these guarantees. Custom processors should pass the full reserved job through NotificationQueueLease::renew/finish. Stop old workers before upgrading and review ambiguous external sends: SMTP is outside database transactions, so crash recovery can duplicate an accepted message. Verify the effective package version; older packages lacked recovery. Inbox writes and completion remain transactional.

`NotificationQueueWorker` clamps batch sizes to 20–500 (default 120). Its `dispatchAsync` launches a processor class, not an arbitrary serialized configured instance. Register/project-autoload the supported processor and verify its dependencies; do not assume constructor injection from a prior instance survives that path.

If a NotificationQueueModel subclass replaces reserve/persistence, adapt its leased methods too or implement the original repository interface independently. Inheriting the native model also inherits its lease contract; a replacement that returns jobs without a generation cannot be treated as a valid native reservation.

For campaigns, use their existing audience/dispatch services; do not loop over recipients or redesign scheduling as an incidental notification fix. For worker scheduling, consult the effective package's cron/Async guides without importing all task infrastructure into a simple notice.
