<?php

namespace GFrame\Auth;

use Exception;
use GFrame\Auth\Contracts\AccountDeactivationPolicy;
use GFrame\Auth\Contracts\SelfAccountRepository;
use GFrame\Session\ActiveSessionRegistry;
use GFrame\Session\SessionRuntime;

final class SelfAccountService
{
    private ?ActiveSessionRegistry $sessions;

    public function __construct(
        private readonly SelfAccountRepository $accounts = new UserModel(),
        private readonly ?AccountDeactivationPolicy $deactivationPolicy = null,
        private readonly PasswordPolicy $passwords = new PasswordPolicy(),
        ?ActiveSessionRegistry $sessions = null
    ) {
        $this->sessions = $sessions ?? SessionRuntime::registry();
    }

    public function profile(int $userID): array
    {
        if ($userID <= 0) {
            return $this->error('not_found');
        }

        try {
            $account = $this->accounts->findAccountByID($userID);
            if ($account === null) {
                return $this->error('not_found');
            }

            unset($account['password'], $account['token']);
            return ['status' => 'success', 'code' => 'account_loaded', 'data' => $account];
        } catch (Exception $exception) {
            return $this->exception($exception, 'account_load_failed');
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
            $account = $this->accounts->findAccountByID($userID);
            if ($account === null) {
                return $this->error('not_found');
            }
            if (!password_verify($currentPassword, (string)($account['password'] ?? ''))) {
                return $this->error('invalid_current_password');
            }

            $this->accounts->updateAccountPassword($userID, $this->passwords->hash($newPassword));
            if ($this->sessions !== null) {
                $this->sessions->revokeUser($userID);
            }
            return ['status' => 'success', 'code' => 'password_updated'];
        } catch (Exception $exception) {
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
            $account = $this->accounts->findAccountByID($userID);
            if ($account === null) {
                return $this->error('not_found');
            }
            $policy = $this->deactivationPolicy
                ?? ($this->accounts instanceof AccountDeactivationPolicy ? $this->accounts : null);
            $canDeactivate = $policy !== null
                ? $policy->canDeactivateAccount($account)
                : (string)($account['role'] ?? '') !== SystemRole::SUPERADMINISTRATOR;
            if (!$canDeactivate) {
                return ['status' => 'unauthorized', 'code' => 'protected_account'];
            }
            if (!password_verify($password, (string)($account['password'] ?? ''))) {
                return $this->error('invalid_current_password');
            }

            $this->accounts->deactivateAccount($userID);
            if ($this->sessions !== null) {
                $this->sessions->revokeUser($userID, true);
            }
            return ['status' => 'success', 'code' => 'account_deactivated'];
        } catch (Exception $exception) {
            return $this->exception($exception, 'account_deactivation_failed');
        }
    }

    private function error(string $code): array
    {
        return ['status' => 'error', 'code' => $code];
    }

    private function exception(Exception $exception, string $code): array
    {
        error_log('[GFrame Self Account] ' . $exception->getMessage());
        return $this->error($code);
    }
}
