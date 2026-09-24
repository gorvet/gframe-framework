<?php

namespace GFrame\Auth;

use GFrame\Auth\Contracts\AuthUserRepository;
use Throwable;

final class AuthService
{
    public function __construct(
        private readonly AuthUserRepository $users,
        private readonly PasswordPolicy $passwords = new PasswordPolicy(),
        private readonly TokenManager $tokens = new TokenManager(),
        private readonly string $pendingStatus = 'unverify',
        private readonly string $activeStatus = 'verify',
        private readonly string $suspendedStatus = 'suspended'
    ) {
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
                $this->displayNameFromEmail($email),
                $token,
                $this->tokens->issuedAt()
            );

            if ($userID <= 0) {
                return $this->error('register_failed');
            }

            return ['status' => 'success', 'code' => 'account_registered', 'user_id' => $userID, 'token' => $token];
        } catch (Throwable $exception) {
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
            if ((string)($user['status'] ?? '') === $this->suspendedStatus) {
                return $this->error('suspended_account');
            }

            $this->users->updateAuthUser((int)$user['user_id'], [
                'status' => $this->activeStatus,
                'token' => $this->tokens->issue(),
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'account_verified'];
        } catch (Throwable $exception) {
            return $this->exception($exception, 'verification_failed');
        }
    }

    public function requestRecovery(string $email): array
    {
        try {
            $user = $this->users->findByEmail($this->normalizeEmail($email));
            if ($user === null || (string)($user['status'] ?? '') === $this->suspendedStatus) {
                return ['status' => 'success', 'code' => 'recovery_requested'];
            }

            $token = $this->tokens->issue();
            $this->users->updateAuthUser((int)$user['user_id'], [
                'token' => $token,
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'recovery_requested', 'token' => $token];
        } catch (Throwable $exception) {
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

            $this->users->updateAuthUser((int)$user['user_id'], [
                'password' => $this->passwords->hash($password),
                'token' => $this->tokens->issue(),
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'password_reset'];
        } catch (Throwable $exception) {
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

            $name = trim((string)($user['name'] ?? '')) ?: $this->displayNameFromEmail((string)$user['email']);
            $firstLogin = empty($user['last_login']);
            $this->users->updateAuthUser((int)$user['user_id'], [
                'name' => $name,
                'last_login' => date('Y-m-d H:i:s'),
            ]);

            unset($user['password'], $user['token']);
            $user['name'] = $name;

            return ['status' => 'success', 'code' => 'authenticated', 'user' => $user, 'first_login' => $firstLogin];
        } catch (Throwable $exception) {
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
        } catch (Throwable $exception) {
            return $this->exception($exception, 'verification_request_failed');
        }
    }

    public function displayNameFromEmail(string $email, string $fallback = 'Usuario'): string
    {
        $local = trim((string)strtok(trim($email), '@'));
        if ($local === '') {
            return $fallback;
        }

        return mb_strtoupper(mb_substr($local, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($local, 1, null, 'UTF-8');
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

    private function exception(Throwable $exception, string $code): array
    {
        error_log('[GFrame Auth] ' . $exception->getMessage());
        return ['status' => 'error', 'code' => $code];
    }
}
