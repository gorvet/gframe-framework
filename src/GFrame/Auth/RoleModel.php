<?php

namespace GFrame\Auth;

class RoleModel extends \ORM
{
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    protected $fillable = ['role_id', 'permission_id', 'name', 'slug', 'is_system'];

    public function findUserRole(int $userID): ?array
    {
        if ($userID <= 0) {
            return null;
        }
        $rows = self::queryTable('users')
            ->reset()->useStrictComparison(false)
            ->select('roles.role_id', 'roles.name', 'roles.slug', 'roles.is_system')
            ->join('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('users.user_id', '=', $userID)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function findRoleByID(int $roleID): ?array
    {
        if ($roleID <= 0) {
            return null;
        }
        $rows = $this->reset()->useStrictComparison(false)
            ->select('role_id', 'name', 'slug', 'is_system')
            ->where('role_id', '=', $roleID)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function findRoleBySlug(string $slug): ?array
    {
        $slug = mb_strtolower(trim($slug), 'UTF-8');
        if ($slug === '') {
            return null;
        }
        $rows = $this->reset()->useStrictComparison(false)
            ->select('role_id', 'name', 'slug', 'is_system')
            ->where('slug', '=', $slug)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function roleHasPermission(int $roleID, string $permission): bool
    {
        if ($roleID <= 0 || trim($permission) === '') {
            return false;
        }
        $rows = self::queryTable('role_permissions')->reset()->useStrictComparison(false)
            ->select('role_permissions.role_id')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', '=', $roleID)
            ->where('permissions.slug', '=', mb_strtolower(trim($permission), 'UTF-8'))
            ->limit(1)->get();
        return isset($rows[0]);
    }

    public function countUsersWithRole(int $roleID): int
    {
        return $roleID <= 0 ? 0 : (int)self::queryTable('users')->reset()
            ->useStrictComparison(false)->where('role_id', '=', $roleID)->count('*');
    }

    public function createRole(string $name, string $slug, bool $isSystem = false): int
    {
        return (int)(new static([
            'name' => trim($name),
            'slug' => mb_strtolower(trim($slug), 'UTF-8'),
            'is_system' => $isSystem ? 1 : 0,
        ]))->insert();
    }

    public function createPermission(string $name, string $slug): int
    {
        return (int)(new static([
            'name' => trim($name),
            'slug' => mb_strtolower(trim($slug), 'UTF-8'),
        ]))->fromTable('permissions')->insert();
    }

    public function assignPermission(int $roleID, int $permissionID): void
    {
        $query = self::queryTable('role_permissions')->reset()->useStrictComparison(false)
            ->where('role_id', '=', $roleID)->where('permission_id', '=', $permissionID);
        if ($query->exists()) {
            return;
        }
        (new static(['role_id' => $roleID, 'permission_id' => $permissionID]))
            ->fromTable('role_permissions')->insert();
    }

    public function assignRole(int $userID, int $roleID): void
    {
        self::queryTable('users')->reset()->useStrictComparison(false)
            ->where('user_id', '=', $userID)->update(['role_id' => $roleID]);
    }

    public function deleteRole(int $roleID): void
    {
        $this->reset()->useStrictComparison(false)->where('role_id', '=', $roleID)->deleteWhere();
    }
}
