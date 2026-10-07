<?php

namespace GFrame\Auth;

use GFrame\Config\ConfigRepository;
use GFrame\Mail\MailService;
use GFrame\Notifications\NotificationModel;
use GFrame\Notifications\NotificationService;

class RegistrationAdminNotifier
{
    public function notify(int $userID, string $email): void
    {
        $settings = (array)ConfigRepository::get('auth.registration_admin_notice', []);
        if (empty($settings['enabled']) || $userID <= 0) return;
        $channels = array_intersect((array)($settings['channels'] ?? ['inbox']), ['email', 'inbox']);
        if ($channels === []) return;
        try {
            foreach ($this->recipients((array)($settings['user_ids'] ?? [])) as $recipient) {
                foreach (array_unique($channels) as $channel) {
                    try {
                        $result = $this->deliver($channel, $recipient, $email);
                        if (($result['status'] ?? '') !== 'success') error_log('[GFrame Auth] Administrative registration notice failed.');
                    } catch (\Throwable $exception) {
                        error_log('[GFrame Auth] Administrative registration notice failed.');
                    }
                }
            }
        } catch (\Throwable $exception) {
            error_log('[GFrame Auth] Administrative registration recipient lookup failed.');
        }
    }

    protected function recipients(array $userIDs): array
    {
        $query = UserModel::queryTable('users')->reset()->useStrictComparison(false)
            ->select('user_id', 'email')->where('status', '=', 'verify');
        if ($userIDs !== []) {
            $userIDs = array_values(array_unique(array_filter(array_map('intval', $userIDs), static fn(int $id): bool => $id > 0)));
            if ($userIDs === []) return [];
            $query->whereIn('user_id', $userIDs);
        }
        $roles = new RoleModel();
        $recipients = [];
        foreach ($query->get() as $row) {
            $row = (array)$row;
            $authorization = $roles->authorizationForUser((int)$row['user_id']);
            if (!empty($authorization['bypass']) || in_array('admin.access', (array)($authorization['permissions'] ?? []), true)) $recipients[] = $row;
        }
        return $recipients;
    }

    protected function deliver(string $channel, array $recipient, string $email): array
    {
        $title = 'Nueva cuenta registrada';
        $message = 'Se ha creado una cuenta con el correo ' . $email . '. La cuenta todavía debe verificar su correo.';
        if ($channel === 'inbox') {
            return (new NotificationService(new NotificationModel()))->notify((int)$recipient['user_id'], [
                'type' => 'auth.registration', 'title' => $title, 'message' => $message,
            ]);
        }
        return (new MailService())->sendHtmlAsync((string)$recipient['email'], $title,
            '<h1>' . $title . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>');
    }
}
