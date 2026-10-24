<?php

namespace GFrame\Mail;

use GFrame\Mail\Contracts\MailSender;
use GFrame\Modules\ModuleRuntime;
use GFrame\Notifications\Contracts\NotificationQueueRepository;
use GFrame\Notifications\NotificationQueueModel;

/** Entrega de enlaces de acceso sin crear un proceso por solicitud. */
final class AccessMailService
{
    public function __construct(private readonly MailSender $mail = new MailService(), private readonly ?NotificationQueueRepository $queue = null) {}

    public function send(string $recipient, string $subject, array $variables, string $name, string $token): array
    {
        if ($this->queue !== null || ModuleRuntime::isInstalled('notifications-email')) {
            try {
                $id = ($this->queue ?? new NotificationQueueModel())->enqueue([
                    'tenant_id' => null, 'channel' => 'email', 'recipient' => $recipient,
                    'payload' => ['subject' => $subject, 'template' => 'mailTemplate', 'variables' => $variables,
                        'recipient_name' => $name, 'access_token_hash' => hash('sha256', $token), 'expires_at' => time() + 86400],
                ]);
                return $id > 0 ? ['status' => 'success', 'code' => 'notification_queued'] : ['status' => 'error', 'code' => 'notification_queue_failed'];
            } catch (\Exception $exception) {
                error_log('[GFrame Access Mail] No se pudo persistir el correo de acceso.');
                return ['status' => 'error', 'code' => 'notification_queue_failed'];
            }
        }
        // Sin el módulo opcional, SMTP se ejecuta aquí, con timeout por operación.
        return $this->mail->sendTemplate($recipient, $subject, 'mailTemplate', $variables, ['recipient_name' => $name, 'timeout' => 10]);
    }
}
