<?php

namespace GFrame\Notifications\Email;

use GFrame\Mail\Contracts\MailSender;
use GFrame\Mail\MailService;
use GFrame\Notifications\Contracts\NotificationTransport;
use RuntimeException;

final class EmailNotificationTransport implements NotificationTransport
{
    public function __construct(private readonly ?MailSender $mail = null) {}

    public function send(array $notification): void
    {
        $payload = (array)($notification['payload'] ?? []);
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
