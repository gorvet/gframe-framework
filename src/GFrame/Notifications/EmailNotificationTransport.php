<?php

namespace GFrame\Notifications;

use GFrame\Notifications\Contracts\NotificationTransport;
use RuntimeException;

final class EmailNotificationTransport implements NotificationTransport
{
    /** @var callable(string,string,string,?string):string */
    private $sender;

    public function __construct(?callable $sender = null)
    {
        $this->sender = $sender ?? static function (
            string $recipient,
            string $subject,
            string $body,
            ?string $replyTo
        ): string {
            $email = new \Email();
            return (string)$email->sendMail($recipient, $subject, $body, $replyTo ?? (string)M_From);
        };
    }

    public function send(array $notification): void
    {
        if (($notification['channel'] ?? '') !== 'email') {
            throw new RuntimeException('El transporte de correo solo admite el canal email.');
        }

        $payload = (array)($notification['payload'] ?? []);
        $recipient = trim((string)($notification['recipient'] ?? ''));
        $subject = trim((string)($payload['subject'] ?? ''));
        $body = (string)($payload['body'] ?? '');
        $replyTo = isset($payload['reply_to']) ? trim((string)$payload['reply_to']) : null;
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $subject === '' || trim($body) === '') {
            throw new RuntimeException('La notificación por correo está incompleta.');
        }

        $result = ($this->sender)($recipient, $subject, $body, $replyTo);
        if ($result !== 'okMailSend') {
            throw new RuntimeException($result !== '' ? $result : 'No se pudo enviar el correo.');
        }
    }
}
