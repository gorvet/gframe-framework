<?php

namespace GFrame\Auth;

use GFrame\Config\ConfigRepository;
use Exception;

final class AuthService
{
    private readonly bool $passwordExpirationEnabled;
    private readonly int $passwordExpirationDays;

    public function __construct(
        private readonly UserModel $users,
        private readonly PasswordPolicy $passwords = new PasswordPolicy(),
        private readonly TokenManager $tokens = new TokenManager(),
        private readonly string $pendingStatus = 'unverify',
        private readonly string $activeStatus = 'verify',
        private readonly string $suspendedStatus = 'suspended',
        ?bool $passwordExpirationEnabled = null,
        ?int $passwordExpirationDays = null
    ) {
        $this->passwordExpirationEnabled = $passwordExpirationEnabled
            ?? (bool)ConfigRepository::get('auth.password_expiration.enabled', false);
        $this->passwordExpirationDays = max(1, $passwordExpirationDays
            ?? (int)ConfigRepository::get('auth.password_expiration.days', 90));
    }

    public function register(string $email, string $password): array
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return $this->error('invalid_email');
        }
        if (!$this->passwords->accepts($password)) {
            return $this->error('invalid_password');
        }

        try {
            if ($this->users->emailExists($email)) {
                return $this->error('user_exists');
            }

            $token = $this->tokens->issue();
            $userID = $this->users->createPendingUser(
                $email,
                $this->passwords->hash($password),
                $token,
                $this->tokens->issuedAt()
            );

            if ($userID <= 0) {
                return $this->error('register_failed');
            }

            return ['status' => 'success', 'code' => 'account_registered', 'user_id' => $userID, 'token' => $token];
        } catch (Exception $exception) {
            return $this->exception($exception, 'register_failed');
        }
    }

    public function verify(string $token): array
    {
        try {
            $user = $this->users->findByToken(trim($token));
            if ($user === null) {
                return $this->error('invalid_token');
            }
            if (in_array((string)($user['status'] ?? ''), [$this->suspendedStatus, 'disabled'], true)) {
                return $this->error('suspended_account');
            }

            $this->users->updateAuthUser((int)$user['user_id'], [
                'status' => $this->activeStatus,
                'token' => $this->tokens->issue(),
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'account_verified'];
        } catch (Exception $exception) {
            return $this->exception($exception, 'verification_failed');
        }
    }

    public function requestRecovery(string $email): array
    {
        try {
            $user = $this->users->findByEmail($this->normalizeEmail($email));
            if ($user === null || in_array((string)($user['status'] ?? ''), [$this->suspendedStatus, 'disabled'], true)) {
                return ['status' => 'success', 'code' => 'recovery_requested'];
            }

            $token = $this->tokens->issue();
            $this->users->updateAuthUser((int)$user['user_id'], [
                'token' => $token,
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'recovery_requested', 'token' => $token];
        } catch (Exception $exception) {
            return $this->exception($exception, 'recovery_failed');
        }
    }

    public function resetPassword(string $token, string $password): array
    {
        if (!$this->passwords->accepts($password)) {
            return $this->error('invalid_password');
        }

        try {
            $user = $this->users->findByToken(trim($token));
            if ($user === null || !$this->tokens->isValidTimestamp((string)($user['token_updated_at'] ?? ''))) {
                return $this->error('invalid_token');
            }
            if (in_array((string)($user['status'] ?? ''), [$this->suspendedStatus, 'disabled'], true)) {
                return $this->error('suspended_account');
            }

            $this->users->updateAuthUser((int)$user['user_id'], [
                'password' => $this->passwords->hash($password),
                'token' => $this->tokens->issue(),
                'token_updated_at' => $this->tokens->issuedAt(),
                'password_changed_at' => date('Y-m-d H:i:s'),
                'force_password_change' => false,
            ]);

            return ['status' => 'success', 'code' => 'password_reset'];
        } catch (Exception $exception) {
            return $this->exception($exception, 'password_reset_failed');
        }
    }

    public function authenticate(string $email, string $password): array
    {
        try {
            $user = $this->users->findByEmail($this->normalizeEmail($email));
            if ($user === null || !password_verify($password, (string)($user['password'] ?? ''))) {
                return $this->error('invalid_user');
            }

            $status = (string)($user['status'] ?? '');
            if ($status === $this->pendingStatus) {
                return $this->error('unverified_account');
            }
            if ($status === $this->suspendedStatus) {
                return $this->error('suspended_account');
            }
            if ($status !== $this->activeStatus) {
                return $this->error('invalid_user');
            }

            $firstLogin = empty($user['last_login']);
            $this->users->updateAuthUser((int)$user['user_id'], [
                'last_login' => date('Y-m-d H:i:s'),
            ]);

            unset($user['password'], $user['token']);

            $mustChangePassword = !empty($user['force_password_change'])
                || $this->passwordHasExpired((string)($user['password_changed_at'] ?? ''));

            return [
                'status' => 'success',
                'code' => $mustChangePassword ? 'password_change_required' : 'authenticated',
                'user' => $user,
                'first_login' => $firstLogin,
                'must_change_password' => $mustChangePassword,
            ];
        } catch (Exception $exception) {
            return $this->exception($exception, 'authentication_failed');
        }
    }

    public function requestVerification(string $email): array
    {
        try {
            $user = $this->users->findByEmail($this->normalizeEmail($email));
            if ($user === null) {
                return ['status' => 'success', 'code' => 'verification_requested'];
            }

            $status = (string)($user['status'] ?? '');
            if ($status === $this->suspendedStatus) {
                return $this->error('suspended_account');
            }
            if ($status !== $this->pendingStatus) {
                return $this->error('already_verified');
            }

            $token = $this->tokens->issue();
            $this->users->updateAuthUser((int)$user['user_id'], [
                'token' => $token,
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'verification_requested', 'token' => $token];
        } catch (Exception $exception) {
            return $this->exception($exception, 'verification_request_failed');
        }
    }

    private function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function error(string $code): array
    {
        return ['status' => 'error', 'code' => $code];
    }

    private function exception(Exception $exception, string $code): array
    {
        error_log('[GFrame Auth] ' . $exception->getMessage());
        return ['status' => 'error', 'code' => $code];
    }

    private function passwordHasExpired(string $changedAt): bool
    {
        if (!$this->passwordExpirationEnabled || $this->passwordExpirationDays <= 0) {
            return false;
        }

        $changedTimestamp = strtotime($changedAt);
        if ($changedTimestamp === false) {
            return true;
        }

        return $changedTimestamp <= strtotime('-' . $this->passwordExpirationDays . ' days');
    }
}
