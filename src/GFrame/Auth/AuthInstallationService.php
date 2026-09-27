<?php

namespace GFrame\Auth;

use Throwable;

final class AuthInstallationService
{
    public function __construct(
        private readonly UserModel $users,
        private readonly PasswordPolicy $passwords = new PasswordPolicy()
    ) {
    }

    public function createFirstUser(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'code' => 'invalid_email'];
        }
        if (!$this->passwords->accepts($password)) {
            return ['status' => 'error', 'code' => 'invalid_password'];
        }

        try {
            if ($this->users->usersExist()) {
                return ['status' => 'error', 'code' => 'application_already_installed'];
            }

            $roleID = $this->users->findRoleIDBySlug(SystemRole::SUPERADMINISTRATOR);
            if ($roleID === null) {
                return ['status' => 'error', 'code' => 'system_role_missing'];
            }

            $userID = $this->users->createActiveUser(
                $email,
                $this->passwords->hash($password),
                $roleID
            );
            if ($userID <= 0) {
                return ['status' => 'error', 'code' => 'installation_failed'];
            }

            return [
                'status' => 'success',
                'code' => 'superadministrator_created',
                'user_id' => $userID,
                'role_id' => $roleID,
            ];
        } catch (Throwable $exception) {
            error_log('[GFrame Auth Installation] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'installation_failed'];
        }
    }
}
