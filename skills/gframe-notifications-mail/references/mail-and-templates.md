# Mail, Templates and Public Forms

## Configuration and Rendering

`GFrame\Mail\MailService` uses SMTP from environment configuration. Keep credentials in the project's `.env`; do not expose them or return internal SMTP errors to visitors. Configuration validation does not test authentication or connectivity. For a contact form, keep the project's configured From address and pass the visitor through `reply_to`/`reply_name`.

`MailTemplateRegistry` discovers `.html` templates and adjacent `.json` metadata in `app/views/templates/mail/` by default. It combines `MailThemeHelper` parameters with supplied variables and escapes placeholder substitutions as text. Supplying rich HTML in a variable does not make it an unescaped HTML slot. `sendHtml` accepts prepared HTML; the application owns its content/trust contract.

Preserve the standard logo/theme/greeting/footer contracts when customizing templates. The logo URL must be reachable by remote mail clients; a localhost URL is not sufficient. Template IDs are validated registry identifiers, not browser file paths. Application template publication and preservation must follow the project's updater contract.

## Async Is Not a Persistent Delivery Queue

`sendTemplateAsync` and `sendHtmlAsync` pass a serializable Closure to `Async`. The worker boots the project and constructs a new MailService; constructor-injected SMTP/template objects from the launching instance are not transferred. Worker templates must exist in the standard project directory and SMTP must be available in its environment. Use an explicit `APP_URL` for independent CLI processes that construct public links.

`mail_queued` only confirms launch. Template/destination/SMTP errors can occur later and are logged with `[GFrame Mail Async]`; these methods have no automatic retries or queryable task ID. Validate the form before dispatch. Do not describe the result as delivery to the recipient. Workers that already run in the background normally use synchronous Mail rather than launching another Async task.

The current Mail API accepts one recipient per call and supports `recipient_name`, `reply_to`, `reply_name`, `timeout` and optional public-form `rate_limit`. It does not expose attachment, CC or BCC parameters. Preserve codes such as `mail_sent`, `mail_send_failed`, `mail_template_failed`, `invalid_email`, `mail_queue_failed` and configuration failures; add public feedback at the owning HTTP layer when needed.

## Public Contact Recipe

After the controller validates the form and obtains a server-known client address, the existing call is:

```php
$result = $mail->sendTemplateAsync(
    'equipo@example.test',
    'Contacto',
    'contactTemplate',
    ['title' => 'Contacto', 'name' => $name, 'subject' => $subject, 'message' => $message],
    ['reply_to' => $email, 'rate_limit' => ['scope' => 'contact', 'identity' => $clientAddress]]
);
```

Here `$mail` is the configured/injected MailService, the recipient is project-owned, and `$name`, `$subject`, `$message`, `$email`, `$clientAddress` are validated controller arguments. The example does not authorize sending to that address. Keep the route's existing tokens, honeypot and response/feedback contract; use a fake Async executor to test launch without running SMTP.

`rate_limit` is opt-in per call for public contact/query forms. Do not apply it to internal notifications, campaigns, verification or password-recovery messages. Enabling `MAIL_RATE_LIMIT_ENABLED` does not rate-limit calls that omit the option. Identity comes from server-trusted client addressing, not the submitted form or an untrusted forwarded header.

The limiter uses `storage/mail-rate/`, exclusive locks and an HMAC key based on `APP_KEY`. Multiple servers need shared storage supporting those locks for one common quota. Preserve `mail_rate_limited` and `data.retry_after` in seconds, plus invalid/unavailable outcomes. A synchronous send failure or launch failure releases its reservation; SMTP failure after an accepted Async launch does not refund it. The worker does not consume another quota slot.

For internal template sends, omit the rate_limit option and choose sync/Async according to the existing operation. Do not replace a requested notice or campaign with this contact-form recipe.
