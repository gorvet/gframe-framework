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
        $result = ($this->mail ?? new MailService())->sendTemplate(
            trim((string)($notification['recipient'] ?? '')),
            (string)($payload['subject'] ?? $payload['title'] ?? 'Notificación'),
            (string)($payload['template'] ?? 'notification'),
            (array)($payload['variables'] ?? $payload),
            ['recipient_name' => (string)($payload['recipient_name'] ?? '')]
        );
        if (($result['status'] ?? '') !== 'success') {
            throw new RuntimeException((string)($result['code'] ?? 'mail_send_failed'));
        }
    }
}
