<?php

namespace GFrame\Auth;

use Exception;
use GFrame\Session\ActiveSessionRegistry;
use GFrame\Session\SessionRuntime;

final class UserPermissionService
{
    private ?ActiveSessionRegistry $sessions;

    public function __construct(private readonly RoleModel $roles, ?ActiveSessionRegistry $sessions = null)
    {
        $this->sessions = $sessions ?? SessionRuntime::registry();
    }

    public function setOverride(int $actorID, int $userID, string $permission, string $effect, int $tenantID = 0): array
    {
        $permission = mb_strtolower(trim($permission), 'UTF-8');
        if (!$this->isSuperadministrator($actorID)) return ['status' => 'unauthorized', 'code' => 'forbidden'];
        if ($userID <= 0 || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $permission) !== 1 || !in_array($effect, ['allow', 'deny'], true)) return ['status' => 'error', 'code' => 'invalid_permission_override'];
        try {
            $this->roles->setPermissionOverride($userID, $permission, $effect, max(0, $tenantID));
            $version = $this->roles->incrementUserAuthorizationVersion($userID);
            $this->sessions?->publishUserAuthorizationVersion($userID, $version);
            return ['status' => 'success', 'code' => 'permission_override_updated'];
        } catch (Exception $exception) {
            error_log('[GFrame User Permissions] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'permission_override_failed'];
        }
    }

    public function removeOverride(int $actorID, int $userID, string $permission, int $tenantID = 0): array
    {
        $permission = mb_strtolower(trim($permission), 'UTF-8');
        if (!$this->isSuperadministrator($actorID)) return ['status' => 'unauthorized', 'code' => 'forbidden'];
        if ($userID <= 0 || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $permission) !== 1) return ['status' => 'error', 'code' => 'invalid_permission_override'];
        try {
            $this->roles->removePermissionOverride($userID, $permission, max(0, $tenantID));
            $version = $this->roles->incrementUserAuthorizationVersion($userID);
            $this->sessions?->publishUserAuthorizationVersion($userID, $version);
            return ['status' => 'success', 'code' => 'permission_override_removed'];
        } catch (Exception $exception) {
            error_log('[GFrame User Permissions] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'permission_override_failed'];
        }
    }

    public function assignTenantRole(int $actorID, int $userID, int $tenantID, int $roleID): array
    {
        if ($userID <= 0 || $tenantID <= 0 || $roleID <= 0) return ['status' => 'error', 'code' => 'invalid_tenant_role_assignment'];
        try {
            $superadministrator = $this->isSuperadministrator($actorID);
            if (!$superadministrator && !$this->isTenantOwner($actorID, $tenantID)) return ['status' => 'unauthorized', 'code' => 'forbidden'];
            $role = $this->roles->findRoleByID($roleID);
            if ($role === null) return ['status' => 'error', 'code' => 'role_not_found'];
            $slug = (string)($role['slug'] ?? '');
            if ($slug === SystemRole::SUPERADMINISTRATOR) return ['status' => 'error', 'code' => 'superadministrator_role_protected'];
            if ($slug === 'owner') return ['status' => 'error', 'code' => 'owner_role_requires_tenant_creation'];
            if (!$superadministrator && $slug !== 'gestor') return ['status' => 'unauthorized', 'code' => 'forbidden'];
            $existing = $this->roles->findTenantMembership($userID, $tenantID);
            if ((string)($existing['slug'] ?? '') === 'owner') return ['status' => 'error', 'code' => 'owner_membership_protected'];
            $this->roles->assignTenantRole($userID, $tenantID, $roleID);
            $version = $this->roles->incrementUserAuthorizationVersion($userID);
            $this->sessions?->publishUserAuthorizationVersion($userID, $version);
            return ['status' => 'success', 'code' => 'tenant_role_assigned'];
        } catch (Exception $exception) {
            error_log('[GFrame User Permissions] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'tenant_role_assignment_failed'];
        }
    }

    public function deactivateTenantMembership(int $actorID, int $userID, int $tenantID): array
    {
        if ($userID <= 0 || $tenantID <= 0) return ['status' => 'error', 'code' => 'invalid_tenant_membership'];
        try {
            $membership = $this->roles->findTenantMembership($userID, $tenantID);
            if ($membership === null || (int)($membership['is_active'] ?? 0) !== 1) return ['status' => 'error', 'code' => 'tenant_membership_not_found'];
            if ((string)($membership['slug'] ?? '') === 'owner') return ['status' => 'error', 'code' => 'owner_membership_protected'];
            $canManage = $this->isSuperadministrator($actorID) || $this->isTenantOwner($actorID, $tenantID);
            $canLeave = $actorID === $userID && (string)($membership['slug'] ?? '') === 'gestor';
            if (!$canManage && !$canLeave) return ['status' => 'unauthorized', 'code' => 'forbidden'];
            if (!$this->isSuperadministrator($actorID) && !$canLeave && (string)($membership['slug'] ?? '') !== 'gestor') return ['status' => 'unauthorized', 'code' => 'forbidden'];
            $this->roles->deactivateTenantMembership($userID, $tenantID);
            $version = $this->roles->incrementUserAuthorizationVersion($userID);
            $this->sessions?->publishUserAuthorizationVersion($userID, $version);
            return ['status' => 'success', 'code' => 'tenant_membership_deactivated'];
        } catch (Exception $exception) {
            error_log('[GFrame User Permissions] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'tenant_membership_update_failed'];
        }
    }

    private function isSuperadministrator(int $userID): bool
    {
        $role = $this->roles->findUserRole($userID);
        return mb_strtolower(trim((string)($role['slug'] ?? '')), 'UTF-8') === SystemRole::SUPERADMINISTRATOR;
    }

    private function isTenantOwner(int $userID, int $tenantID): bool
    {
        $membership = $this->roles->findTenantMembership($userID, $tenantID);
        return (int)($membership['is_active'] ?? 0) === 1 && (string)($membership['slug'] ?? '') === 'owner';
    }
}
