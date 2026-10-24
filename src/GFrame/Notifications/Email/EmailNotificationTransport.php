<?php

namespace GFrame\Notifications\Email;

use GFrame\Mail\Contracts\MailSender;
use GFrame\Mail\MailService;
use GFrame\Notifications\Contracts\NotificationTransport;
use RuntimeException;

final class EmailNotificationTransport implements NotificationTransport
{
    public function __construct(private readonly ?MailSender $mail = null, private readonly ?\GFrame\Auth\AuthModel $auth = null) {}

    public function send(array $notification): void
    {
        $payload = (array)($notification['payload'] ?? []);
        if (isset($payload['access_token_hash'])) {
            $user = ($this->auth ?? new \GFrame\Auth\AuthModel())->findByEmail((string)($notification['recipient'] ?? ''));
            if ((int)($payload['expires_at'] ?? 0) <= time() || $user === null
                || in_array((string)($user['status'] ?? ''), ['suspended', 'disabled'], true)
                || !(new \GFrame\Auth\TokenManager())->isValidTimestamp((string)($user['token_updated_at'] ?? ''))
                || !hash_equals((string)$payload['access_token_hash'], hash('sha256', (string)($user['token'] ?? '')))) {
                throw new \GFrame\Mail\ExpiredAccessMail('access_mail_expired');
            }
        }
        $variables = (array)($payload['variables'] ?? $payload);
        $actionURL = (string)($payload['action_url'] ?? $variables['action_url'] ?? '');
        $variables['action_url'] = preg_match('~^https?://[^\s]+$~i', $actionURL) === 1 ? $actionURL : '';
        $variables['action_display'] = $variables['action_url'] !== '' ? 'block' : 'none';
        $result = ($this->mail ?? new MailService())->sendTemplate(
            trim((string)($notification['recipient'] ?? '')),
            (string)($payload['subject'] ?? $payload['title'] ?? 'Notificación'),
            (string)($payload['template'] ?? 'notification'),
            $variables,
            ['recipient_name' => (string)($payload['recipient_name'] ?? '')]
        );
        if (($result['status'] ?? '') !== 'success') {
            throw new RuntimeException((string)($result['code'] ?? 'mail_send_failed'));
        }
    }
}
