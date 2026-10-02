<?php

namespace GFrame\Auth;

use GFrame\Auth\Contracts\AccountDeactivationPolicy;
use GFrame\Auth\Contracts\SelfAccountRepository;
use GFrame\Auth\Contracts\UserAdministrationRepository;
use GFrame\Auth\Contracts\UserModerationRepository;
use RuntimeException;

class UserModel extends \ORM implements SelfAccountRepository, AccountDeactivationPolicy, UserAdministrationRepository, UserModerationRepository
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

    public function findAccountByID(int $userID): ?array
    {
        return $userID <= 0 ? null : $this->findIdentity('users.user_id', (string)$userID);
    }

    public function updateAccountPassword(int $userID, string $passwordHash): void
    {
        $now = date('Y-m-d H:i:s');
        $this->updateAuthUser($userID, [
            'password' => $passwordHash,
            'token' => bin2hex(random_bytes(32)),
            'token_updated_at' => $now,
            'password_changed_at' => $now,
            'force_password_change' => false,
        ]);
    }

    public function deactivateAccount(int $userID): void
    {
        $this->updateAuthUser($userID, ['status' => 'disabled']);
    }

    public function canDeactivateAccount(array $account): bool
    {
        return (string)($account['role'] ?? '') !== SystemRole::SUPERADMINISTRATOR;
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

    public function usersExist(): bool
    {
        return $this->reset()->count('*') > 0;
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

    public function createActiveUser(string $email, string $passwordHash, int $roleID): int
    {
        $now = date('Y-m-d H:i:s');
        return (int)(new static([
            'email' => $email,
            'password' => $passwordHash,
            'role_id' => $roleID,
            'status' => 'verify',
            'token' => '',
            'token_updated_at' => $now,
            'password_changed_at' => $now,
            'force_password_change' => false,
        ]))->insert();
    }

    public function paginateUsers(int $page, int $perPage, string $search = '', string $role = '', string $status = ''): array
    {
        $countQuery = $this->reset();
        if ($search !== '') {
            $countQuery->whereLike('email', $search);
        }
        if ($role !== '') {
            $countQuery->where('role_id', '=', (int)($this->findRoleIDBySlug($role) ?? 0));
        }
        if ($status !== '') {
            $countQuery->where('status', '=', $status);
        }
        $total = (int)$countQuery->count('*');
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $query = $this->reset()
            ->select('users.user_id', 'users.email', 'users.status', 'roles.role_id', 'roles.name AS role_name', 'roles.slug AS role')
            ->join('roles', 'roles.role_id', '=', 'users.role_id');
        if ($search !== '') {
            $query->whereLike('users.email', $search);
        }
        if ($role !== '') {
            $query->where('roles.slug', '=', $role);
        }
        if ($status !== '') {
            $query->where('users.status', '=', $status);
        }

        return [
            'data' => array_map(
                static fn(array $row): array => $row,
                $query->orderBy('users.user_id', 'DESC')->paginate($page, $perPage)
            ),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $lastPage,
                'search' => $search,
                'role' => $role,
                'status' => $status,
            ],
        ];
    }

    public function findUserByID(int $userID): ?array
    {
        $rows = $this->reset()
            ->select('users.user_id', 'users.email', 'users.status', 'roles.role_id', 'roles.name AS role_name', 'roles.slug AS role')
            ->join('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('users.user_id', '=', $userID)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function setActive(int $userID, bool $active): void
    {
        $this->updateAuthUser($userID, ['status' => $active ? 'verify' : 'disabled']);
    }

    public function assignRole(int $userID, int $roleID): void
    {
        $result = $this->reset()->where('user_id', '=', $userID)->update(['role_id' => $roleID]);
        if (!in_array($result['status'] ?? '', ['updated', 'no_change'], true)) {
            throw new RuntimeException('No se pudo actualizar el rol.');
        }
    }

    public function setAccountStatus(int $userID, string $status): void
    {
        if (!in_array($status, ['verify', 'suspended'], true)) {
            throw new RuntimeException('Estado administrativo inválido.');
        }
        $this->updateAuthUser($userID, ['status' => $status]);
    }

    public function deleteAccount(int $userID): void
    {
        $connection = $this->resolveCurrentConnectionName();
        self::beginTransaction($connection);
        try {
            foreach (['tenant_memberships', 'gframe_sessions', 'users'] as $table) {
                $result = self::queryTable($table)->onConnection($connection)->reset()
                    ->where('user_id', '=', $userID)->deleteWhere();
                if (!in_array($result['status'] ?? '', ['deleted', 'not_found', 'no_change'], true)) {
                    throw new RuntimeException('No se pudo eliminar la cuenta.');
                }
            }
            self::commit($connection);
        } catch (\Exception $exception) {
            self::rollBack($connection);
            throw $exception;
        }
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
