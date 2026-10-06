---
name: gframe-notifications-mail
description: Implement and review GFrame email, inbox notifications and notification delivery queues, including templates, transport selection, scoped recipients, public-form rate limits and existing delivery/error contracts.
---

# GFrame Mail and Notifications

Use this for sending mail, creating inbox notices or integrating existing notification delivery. Campaign audience/scheduling and generic job orchestration are separate tasks; do not add them merely because a feature sends a message.

## Choose the Existing Path

| Requested result | Entry point | What success establishes |
| --- | --- | --- |
| Send mail in the current process | `MailService::sendTemplate` / `sendHtml` | `mail_sent`: SMTP acceptance, not recipient reading or guaranteed inbox delivery |
| Launch mail outside a web request | `sendTemplateAsync` / `sendHtmlAsync` | `mail_queued`: Async launch accepted, not SMTP delivery or a persisted retry queue |
| Create an inbox notice | `NotificationService::notify` | `notification_created`: persisted notice, not email |
| Persist notification delivery work | `NotificationQueueService::enqueue` | `notification_queued`: queued record, not transport completion |

Read [mail and templates](references/mail-and-templates.md) for direct/Async mail or a public contact form. Read [inbox and delivery queues](references/inbox-and-queues.md) for notices, tenant/user access, email transport or batch processing.

## Work From the Effective Package

- Identify the project's loaded GFrame package/version before applying these APIs. Full guides are `docs/mail.md`, `docs/notificaciones.md` and `docs/notifications-email.md` in that package, not relative to a global skill folder.
- For project-specific changes, use application controllers/services, supported runtime overrides and project mail templates. For an authorized framework change, use standalone originals; do not patch an installed Composer copy.
- Resolve actor, recipient and tenant on the server and verify business access. A positive user ID, supplied email or tenant argument is not a membership check.
- Keep existing payload keys, response codes and CSRF/permission checks. Follow the matching backend/frontend skill when changing an HTTP screen; this skill does not duplicate their layout rules.
- Select only the requested channel. An inbox notice does not automatically send mail, and mail does not automatically create an inbox notice.
- Never claim retries, crash recovery, exactly-once delivery, attachments or a tracking API merely because another messaging path supports them.

## Verification

Verify the chosen API, template/payload, recipient/scope and immediate versus eventual result. Use fake `MailSender`, Async or repository collaborators and temporary storage for diagnostics; invoking this skill is not authorization to send real messages or contact production recipients.

Existing relevant tests include `MailTemplatesTest`, `MailRateLimiterTest`, `NotificationEmailTest`, `NotificationInboxTest`, `NotificationModelSqliteTest` and `NotificationDocumentationTest`. Select those affected by the change. Record unavailable SMTP, browser, Redis or real-engine checks separately. Structural validation and local transport doubles do not prove real mail delivery.
