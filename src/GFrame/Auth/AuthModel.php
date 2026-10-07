<?php

namespace GFrame\Auth;

use GFrame\Config\ConfigRepository;
use Exception;
use RuntimeException;

/**
 * Modelo MVC de autenticación extraído de Base Confías y Bebots.
 * Conserva el esquema normalizado y las protecciones de GFrame.
 */
class AuthModel extends \ORM
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    protected $fillable = [
        'user_id',
        'email',
        'password',
        'role_id',
        'status',
        'token',
        'token_updated_at',
        'password_changed_at',
        'force_password_change',
        'last_login',
    ];
    protected $casts = [
        'user_id' => 'int',
        'role_id' => 'int',
        'force_password_change' => 'bool',
    ];

    private readonly bool $passwordExpirationEnabled;
    private readonly int $passwordExpirationDays;

    public function __construct(
        array $attributes = [],
        private readonly PasswordPolicy $passwords = new PasswordPolicy(),
        private readonly TokenManager $tokens = new TokenManager(),
        private readonly string $pendingStatus = 'unverify',
        private readonly string $activeStatus = 'verify',
        private readonly string $suspendedStatus = 'suspended',
        ?bool $passwordExpirationEnabled = null,
        ?int $passwordExpirationDays = null,
        private readonly ?\GFrame\Session\ActiveSessionRegistry $sessions = null,
        private readonly ?RegistrationAdminNotifier $registrationNotifier = null
    ) {
        parent::__construct($attributes);
        $this->passwordExpirationEnabled = $passwordExpirationEnabled
            ?? (bool)ConfigRepository::get('auth.password_expiration.enabled', false);
        $this->passwordExpirationDays = max(1, $passwordExpirationDays
            ?? (int)ConfigRepository::get('auth.password_expiration.days', 90));
    }

    public function registerAcount(string $email, string $password): array
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return $this->error('invalid_email');
        }
        if (!$this->passwords->accepts($password)) {
            return $this->error('invalid_password');
        }

        try {
            if ($this->emailExists($email)) {
                return $this->error('user_exists');
            }

            $token = $this->tokens->issue();
            $userID = $this->createPendingUser(
                $email,
                $this->passwords->hash($password),
                $token,
                $this->tokens->issuedAt()
            );

            if ($userID <= 0) {
                return $this->error('register_failed');
            }

            try {
                ($this->registrationNotifier ?? new RegistrationAdminNotifier())->notify($userID, $email);
            } catch (\Throwable $exception) {
                error_log('[GFrame Auth] Administrative registration notice failed.');
            }
            return ['status' => 'success', 'code' => 'account_registered', 'data' => ['user_id' => $userID, 'token' => $token]];
        } catch (Exception $exception) {
            return $this->exception($exception, 'register_failed');
        }
    }

    public function validateAcount(string $token): array
    {
        try {
            $user = $this->findByToken(trim($token));
            if ($user === null || !$this->tokens->isValidTimestamp((string)($user['token_updated_at'] ?? ''))) {
                return $this->error('invalid_token');
            }
            if (in_array((string)($user['status'] ?? ''), [$this->suspendedStatus, 'disabled'], true)) {
                return $this->error('suspended_account');
            }

            $this->updateAuthUser((int)$user['user_id'], [
                'status' => $this->activeStatus,
                'token' => $this->tokens->issue(),
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'account_verified'];
        } catch (Exception $exception) {
            return $this->exception($exception, 'verification_failed');
        }
    }

    public function recoveryAcount(string $email): array
    {
        try {
            $user = $this->findByEmail($this->normalizeEmail($email));
            if ($user === null || in_array((string)($user['status'] ?? ''), [$this->suspendedStatus, 'disabled'], true)) {
                return ['status' => 'success', 'code' => 'recovery_requested'];
            }

            $token = $this->tokens->issue();
            $this->updateAuthUser((int)$user['user_id'], [
                'token' => $token,
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'recovery_requested', 'data' => ['token' => $token]];
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
            $user = $this->findByToken(trim($token));
            if ($user === null || !$this->tokens->isValidTimestamp((string)($user['token_updated_at'] ?? ''))) {
                return $this->error('invalid_token');
            }
            if (in_array((string)($user['status'] ?? ''), [$this->suspendedStatus, 'disabled'], true)) {
                return $this->error('suspended_account');
            }

            $this->updateAuthUser((int)$user['user_id'], [
                'password' => $this->passwords->hash($password),
                'token' => $this->tokens->issue(),
                'token_updated_at' => $this->tokens->issuedAt(),
                'password_changed_at' => date('Y-m-d H:i:s'),
                'force_password_change' => false,
            ]);

            ($this->sessions ?? \GFrame\Session\SessionRuntime::registry())?->revokeUser((int)$user['user_id']);
            return ['status' => 'success', 'code' => 'password_reset'];
        } catch (Exception $exception) {
            return $this->exception($exception, 'password_reset_failed');
        }
    }

    public function login(string $email, string $password): array
    {
        try {
            $user = $this->findByEmail($this->normalizeEmail($email));
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
            $this->updateAuthUser((int)$user['user_id'], [
                'last_login' => date('Y-m-d H:i:s'),
            ]);

            unset($user['password'], $user['token']);

            $mustChangePassword = !empty($user['force_password_change'])
                || $this->passwordHasExpired((string)($user['password_changed_at'] ?? ''));

            return [
                'status' => 'success',
                'code' => $mustChangePassword ? 'password_change_required' : 'authenticated',
                'data' => [
                    'user' => $user,
                    'first_login' => $firstLogin,
                    'must_change_password' => $mustChangePassword,
                ],
            ];
        } catch (Exception $exception) {
            return $this->exception($exception, 'authentication_failed');
        }
    }

    public function verifyAcount(string $email): array
    {
        try {
            $user = $this->findByEmail($this->normalizeEmail($email));
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
            $this->updateAuthUser((int)$user['user_id'], [
                'token' => $token,
                'token_updated_at' => $this->tokens->issuedAt(),
            ]);

            return ['status' => 'success', 'code' => 'verification_requested', 'data' => ['token' => $token]];
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

    public function emailExists(string $email): bool
    {
        return $this->reset()->where('email', '=', $email)->exists();
    }

    public function createPendingUser(
        string $email,
        string $passwordHash,
        string $token,
        string $issuedAt
    ): int {
        $roleID = $this->findRoleIDBySlug('registered');
        if ($roleID === null) {
            throw new RuntimeException('No está instalado el rol registered.');
        }

        return (int)(new static([
            'email' => $email,
            'password' => $passwordHash,
            'role_id' => $roleID,
            'status' => 'unverify',
            'token' => $token,
            'token_updated_at' => $issuedAt,
            'password_changed_at' => $issuedAt,
            'force_password_change' => false,
        ]))->insert();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findIdentity('users.email', $email);
    }

    public function findByToken(string $token): ?array
    {
        return trim($token) === '' ? null : $this->findIdentity('users.token', $token);
    }

    public function updateAuthUser(int $userID, array $attributes): void
    {
        $allowed = array_intersect_key($attributes, array_flip([
            'password',
            'status',
            'token',
            'token_updated_at',
            'password_changed_at',
            'force_password_change',
            'last_login',
        ]));
        if ($userID <= 0 || $allowed === []) {
            throw new RuntimeException('Actualización de autenticación inválida.');
        }

        if (in_array((string)($allowed['status'] ?? ''), ['suspended', 'disabled'], true)) {
            $allowed['token'] = bin2hex(random_bytes(32));
            $allowed['token_updated_at'] = date('Y-m-d H:i:s');
        }

        $result = $this->reset()
            ->useStrictComparison(false)
            ->where('user_id', '=', $userID)
            ->update($allowed);
        if (!in_array($result['status'] ?? '', ['updated', 'no_change'], true)) {
            throw new RuntimeException('No se pudo actualizar el usuario de autenticación.');
        }
    }

    public function findRoleIDBySlug(string $slug): ?int
    {
        $rows = self::queryTable('roles')
            ->reset()
            ->useStrictComparison(false)
            ->select('role_id')
            ->where('slug', '=', mb_strtolower(trim($slug), 'UTF-8'))
            ->limit(1)
            ->get();

        return isset($rows[0]['role_id']) ? (int)$rows[0]['role_id'] : null;
    }

    private function findIdentity(string $column, string $value): ?array
    {
        $rows = $this->reset()
            ->useStrictComparison(false)
            ->select(
                'users.user_id',
                'users.email',
                'users.password',
                'users.role_id',
                'users.status',
                'users.token',
                'users.token_updated_at',
                'users.password_changed_at',
                'users.force_password_change',
                'users.last_login',
                'roles.slug AS role'
            )
            ->join('roles', 'roles.role_id', '=', 'users.role_id')
            ->where($column, '=', $value)
            ->limit(1)
            ->get();

        return isset($rows[0]) ? (array)$rows[0] : null;
    }
}
