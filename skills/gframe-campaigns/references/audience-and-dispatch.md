# Audience, Dispatch and Recurrence

## Creation Recipe

For a one-off campaign scheduled at a validated UTC `$scheduledAt`, using injected `CampaignService` and an authorized audience:

```php
$created = $campaigns->create([
    'name' => 'Resumen del proyecto',
    'title' => 'Hola {{user_name}}',
    'message' => 'El resumen está disponible.',
    'channels' => ['inbox'],
    'recurrence' => 'once',
    'scheduled_at' => $scheduledAt,
    'audience' => [
        'scope' => 'active', 'tenant_id' => $tenantID,
        'channels' => ['inbox'], 'site_url' => $siteURL,
    ],
], $audience, $tenantID, $createdBy);
```

`$scheduledAt` must be a future UTC date for a scheduled request; convert/validate local input before the call. Omit this field only for an explicitly immediate campaign. `$campaigns` receives `CampaignRepository`, `NotificationQueueRepository`, `CronTaskService` and optional `CampaignRecipientGuard`. `$audience` is an iterable or `CampaignAudienceProvider`, and IDs/URL come from authorized backend context. With standard `CampaignUserAudience`, inject it as both provider and guard. Criteria channels/tenant must match the campaign channels/tenant; the service does not automatically reconcile them.

The standard audience selects verified users with active tenant memberships, and administrators by effective admin access. Manual empty IDs return no users, avoiding the ORM empty-IN behavior. It rechecks eligibility/address when used as the recipient guard. A custom provider must own eligibility; without a guard injected, dispatch does not repeat that business check.

Providers return per-person `recipients` keyed by channel plus `variables`, or single `channel`/`recipient` entries. Normalization deduplicates channel/address pairs case-insensitively; recipient totals count those pairs, not unique humans. A syntactically valid custom channel is not proof of an installed transport.

Without `scheduled_at`, create dispatches the first batch immediately (default 200) and schedules continuation. Inspect `data.dispatch.status`, queued/failed/progress, not just `campaign_started`. UTC scheduled creation returns `campaign_scheduled`; `dispatch()` itself does not enforce future due dates. The native cron handler does. Services do not automatically launch the email worker; standard web actions do so separately and report launch in `meta.email_worker`.

Campaign completed means no pending/processing recipients in preparation, not delivered mail or completed recurrence. Recipient reservations recover after 900 seconds; queue keys identify campaign/recipient. These protections do not establish exactly-once SMTP. Creation, cron registration and dispatch are separate operations; a create error does not guarantee all prior persistence was rolled back. Likewise campaign control calls do not establish an atomic update of all cron/queue state.

## Scheduling and Control

`CampaignSchedule::utc` converts a local date with its named timezone. Invalid dates/timezones can throw; validate at the HTTP boundary. `recurrence` alone does not register a recurring plan. After successful creation call `CampaignRecurrenceModel::register($campaignID, $criteria, $nextAt)` and check its result; use `configure` for existing plans. The native UI performs this registration.

Daily/weekly next dates advance in UTC, so DST may change the local hour. Native recurrence reconstructs `CampaignUserAudience`, creates one occurrence per campaign/date and skips intermediate expired periods rather than sending every missed turn. An injected custom provider/guard does not transfer into the final native cron handlers. Build a project handler/registration when another audience is required, preserving scope and deduplication.

Pause/resume/cancel use campaign ID plus intended tenant and control recurrence tasks too. Cancellation cannot recall queued/delivered messages. Editing completed recurrent campaigns affects future occurrences; recycling creates a new campaign and requires an explicit fresh audience/date. Preserve native content fields, safe action URLs and expiry-per-send semantics.

Preview lists eligible users; “send test” ignores the selected audience and queues only for the authenticated actor, without creating a campaign. Both use existing endpoints/contracts. Verify standard AJAX errors, stale selections, tenant boundaries and user feedback when changing the screen.
