---
name: gframe-campaigns
description: Implement and review GFrame notification campaigns, authorized audiences, scheduling and recurrence, automatic account rules, cooldown history and deactivation lifecycle integration.
---

# GFrame Campaigns

Use this for bulk notification business flows and automatic account rules. Single mail/inbox delivery belongs to the notification/mail specialist; generic worker operation belongs to background jobs. A campaign creates notification jobs, not SMTP delivery.

Read [audience, dispatch and recurrence](references/audience-and-dispatch.md) for campaign creation/control. Read [automatic rules and account lifecycle](references/automatic-and-lifecycle.md) for state events, suppression, history or deactivation. Load both only when the task crosses those responsibilities.

Locate the effective package/version, `resources/modules/notification-campaigns/module.php` and `docs/notification-campaigns.md`. Preserve originals, application overrides and published asset paths. For project-specific UI/controllers/models use supported inheritance and runtime lookup; do not alter an installed package. Existing backend/admin/auth instructions own HTTP, CSRF, layout and authorization conventions.

Resolve actor, tenant, channels and audience on the server. Service calls do not check session permissions. Preserve `notifications.campaigns.view` versus `notifications.campaigns.manage`, and distinguish global null scope from a missing tenant. Keep `user_ids[]`, `campaign_id`, `rule_key` and existing selectors unless changing their consumers is explicitly in scope.

Verify creation, nested dispatch/progress and eventual queue outcomes separately. A completed campaign, Async worker launch or deduplication key does not prove external delivery. Cancellation does not remove jobs already queued. Do not change account deletion policy while adding a campaign recipe.

Use temporary storage and fake audience/queue/cron collaborators for diagnostics. Existing tests include `NotificationCampaignsTest`, `CampaignAudienceTest`, `CampaignRecurrenceTest`, `CampaignRefinementTest`, `CampaignEmailWorkerTest`, `AutomaticCampaignsTest`, `AutomaticCampaignInheritanceTest` and `AccountDeactivationLifecycleTest`. Test lifecycle effects only with temporary users. Record unverified browser, SMTP and deployment scheduling checks; previews and “send test” still enqueue messages and need the task's authorization.
