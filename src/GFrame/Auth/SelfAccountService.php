<?php

namespace GFrame\Auth;

use GFrame\Auth\Contracts\AccountDeactivationPolicy;
use GFrame\Auth\Contracts\SelfAccountRepository;
use Throwable;

final class SelfAccountService
{
    public function __construct(
        private readonly SelfAccountRepository $accounts,
        private readonly ?AccountDeactivationPolicy $deactivationPolicy = null,
        private readonly PasswordPolicy $passwords = new PasswordPolicy(),
        private readonly int $maximumNameLength = 120
    ) {
    }

    public function profile(int $userID): array
    {
        if ($userID <= 0) {
            return $this->error('not_found');
        }

        try {
            $account = $this->accounts->findAccountById($userID);
            if ($account === null) {
                return $this->error('not_found');
            }

            unset($account['password'], $account['token']);
            return ['status' => 'success', 'code' => 'account_loaded', 'data' => $account];
        } catch (Throwable $exception) {
            return $this->exception($exception, 'account_load_failed');
        }
    }

    public function updateProfile(int $userID, string $name): array
    {
        $name = trim($name);
        if ($userID <= 0) {
            return $this->error('not_found');
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') > $this->maximumNameLength) {
            return $this->error('invalid_name');
        }

        try {
            if ($this->accounts->findAccountById($userID) === null) {
                return $this->error('not_found');
            }

            $this->accounts->updateAccountName($userID, $name);
            return ['status' => 'success', 'code' => 'profile_updated'];
        } catch (Throwable $exception) {
            return $this->exception($exception, 'profile_update_failed');
        }
    }

    public function changePassword(
        int $userID,
        string $currentPassword,
        string $newPassword,
        string $confirmation
    ): array {
        if ($userID <= 0) {
            return $this->error('not_found');
        }
        if ($currentPassword === '' || $newPassword === '' || $confirmation === '') {
            return $this->error('empty_field');
        }
        if (!$this->passwords->accepts($newPassword)) {
            return $this->error('invalid_password');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            return $this->error('password_mismatch');
        }

        try {
            $account = $this->accounts->findAccountById($userID);
            if ($account === null) {
                return $this->error('not_found');
            }
            if (!password_verify($currentPassword, (string)($account['password'] ?? ''))) {
                return $this->error('invalid_current_password');
            }

            $this->accounts->updateAccountPassword($userID, $this->passwords->hash($newPassword));
            return ['status' => 'success', 'code' => 'password_updated'];
        } catch (Throwable $exception) {
            return $this->exception($exception, 'password_update_failed');
        }
    }

    public function deactivate(int $userID, string $password): array
    {
        if ($userID <= 0) {
            return $this->error('not_found');
        }
        if ($password === '') {
            return $this->error('empty_field');
        }

        try {
            $account = $this->accounts->findAccountById($userID);
            if ($account === null) {
                return $this->error('not_found');
            }
            if ($this->deactivationPolicy !== null && !$this->deactivationPolicy->canDeactivateAccount($account)) {
                return ['status' => 'unauthorized', 'code' => 'protected_account'];
            }
            if (!password_verify($password, (string)($account['password'] ?? ''))) {
                return $this->error('invalid_current_password');
            }

            $this->accounts->deactivateAccount($userID);
            return ['status' => 'success', 'code' => 'account_deactivated'];
        } catch (Throwable $exception) {
            return $this->exception($exception, 'account_deactivation_failed');
        }
    }

    private function error(string $code): array
    {
        return ['status' => 'error', 'code' => $code];
    }

    private function exception(Throwable $exception, string $code): array
    {
        error_log('[GFrame Self Account] ' . $exception->getMessage());
        return $this->error($code);
    }
}
