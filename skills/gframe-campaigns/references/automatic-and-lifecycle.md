# Automatic Rules and Account Lifecycle

Automatic rules are not ordinary campaign recurrence. `AutomaticCampaignModel` owns definitions, content, eligibility and variables per scope (0 global). Management/history require `notifications.campaigns.manage`. Installed schema/updater rules belong to maintenance; do not create replacement tables incidentally.

For a persisted, authorized account-block event:

```php
$emitted = \GFrame\Notifications\Campaigns\AutomaticCampaignDispatcher::emit(
    'account_blocked',
    $userID,
    'account-block:' . $operationID,
    ['site_url' => $siteURL, 'reason' => 'Contacta con soporte para revisar el acceso.'],
    $tenantID
);
```

User/tenant/operation ID and project URL are trusted server values. Emit after the state update succeeds. Reuse the operation ID for the same event's retries. `automatic_campaign_queued` confirms queueing; disabled/suppressed are successful outcomes without new messages. State requirements differ: suspended, blocked, unverify and disabled respectively. Do not invent blocked-state support in an application that lacks it.

Automatic tenant eligibility checks membership existence, unlike the ordinary audience's active-membership check. Preserve that distinction rather than claiming identical policies. Cooldown (1–3650 days) is shared by automatic, periodic and manual sends for each rule/user/scope, beginning at enqueue. Changing content/event ID does not bypass it. Delivery/history persistence and verification-token renewal follow the transactional native path; skipped verification notices must not renew tokens. Enqueued history is not proof of reading or receiving mail.

Manual automatic sends use backend-selected eligible users and `rule_key`; obsolete manual IDs/reason inputs are not selectors for that endpoint. Manual sending does not require automatic activation but still applies eligibility/cooldown. Hourly review requires a running project cron. It must not be replaced by browser polling.

For project rules extend `AutomaticCampaignModel::definitions`, `eligibleUsers`, `eligible` and `variables`, delegating existing account rules to parent. Inject the model into the runtime controller, use a matching project `AutomaticCampaignCronHandler`, and register that handler; cron does not discover subclasses or controller injection automatically. `AutomaticCampaignDispatcher::emit` supports the custom model argument. The legacy `config/notifications/automatic-campaigns.php` file is not loaded; updates preserve it rather than migrating custom logic automatically.

## Deactivation Boundary

With self-account/campaign modules, `AccountDeactivationLifecycle` registers a real deactivation cycle and immediate email. Default retention is 60 days with a 72-hour warning; configuration changes do not rewrite already saved cycle dates. Reactivation cancels the cycle; a new deactivation begins a new one. Old disabled accounts without a cycle are not assigned retroactive deletion dates.

Deletion requires the user still disabled/eligible, the cycle date due and the warning job marked sent for the required warning duration. Pending/failed mail prevents deletion; a late warning postpones the due date. Disabling the warning rule can prevent automatic deletion. “Sent” remains the transport's confirmation, not recipient reading. Keep these guards, protected-account rules and global lifecycle scope when extending campaigns; do not trigger cleanup/deletion from a notification UI or diagnostic on real users.
