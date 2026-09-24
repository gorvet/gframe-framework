<?php
// Data access for middleware permissions.

class MiddlewareDataProvider extends ORM {
protected $table = 'user_permissions';
protected $primaryKey = 'id';
protected $fillable = [
    'id',
    'permission_id',
    'tenant_id',
    'user_id',
    'permission_level',
    'permissions_json',
    'is_active',
    'invited_by',
];

protected $casts = [
    'tenant_id' => 'int',
    'id' => 'int',
    'user_id' => 'int'
];

public function __construct($attributes = []) {
    parent::__construct($attributes);
}

public function userPermissions(int $userID, ?int $tenantID = null, string $fallbackLevel = ''): array {

    try {
        $usesTenancy = projectPermissionsUseTenancy();
        if ($userID <= 0 || ($usesTenancy && ($tenantID ?? 0) <= 0)) {
            return ['status' => 'unauthorized', 'code' => 'forbidden'];
        }

        $q = $this
            ->reset()
            ->select('permission_level', 'permissions_json')
            ->where('user_id', '=', $userID)
            ->where('is_active', '=', 1);

        if ($usesTenancy) {
            $q->where('tenant_id', '=', (int)$tenantID);
        }

        $row = $q->get();
        if (!isset($row[0])) {
            if ($usesTenancy) {
                $ownerFallback = $this->buildOwnerFallbackPermission($userID, (int)$tenantID);
                if ($ownerFallback !== null) {
                    return ['status' => 'success', 'data' => $ownerFallback];
                }

                return ['status' => 'unauthorized', 'code' => 'not_found'];
            }

            $level = projectPermissionLevel($fallbackLevel);
            if ($level === '') {
                return ['status' => 'unauthorized', 'code' => 'permission'];
            }

            $permissions = projectPermissionsForLevel($level);
            return ['status' => 'success', 'data' => [
                'permission_level' => $level,
                'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'permissions' => $permissions,
                'source' => 'template',
            ]];
        }

        $storedLevel = strtolower(trim((string)($row[0]['permission_level'] ?? '')));
        $permissionLevel = $usesTenancy
            ? projectPermissionLevel($storedLevel)
            : projectPermissionLevel($fallbackLevel, $storedLevel);
        if ($permissionLevel === '') {
            return ['status' => 'unauthorized', 'code' => 'permission'];
        }
        $storedJson = (string)($row[0]['permissions_json'] ?? '');
        $levelChanged = !$usesTenancy && $storedLevel !== $permissionLevel;
        if ($levelChanged) {
            $mergedJson = json_encode(
                projectPermissionsForLevel($permissionLevel),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '{}';
            $didAddKeys = true;
        } else {
            [$mergedJson, $didAddKeys] = $this->mergePermissionsWithTemplate($storedJson, $permissionLevel);
        }

        if ($didAddKeys) {
            $updateQuery = $this
                ->reset()
                ->queryTable($this->table)
                ->where('user_id', '=', $userID)
                ->where('is_active', '=', 1);
            if ($usesTenancy) {
                $updateQuery->where('tenant_id', '=', (int)$tenantID);
            }
            $updateQuery->update([
                'permission_level' => $permissionLevel,
                'permissions_json' => $mergedJson,
            ]);
        }

        $row[0]['permission_level'] = $permissionLevel;
        $row[0]['permissions_json'] = $mergedJson;
        $row[0]['permissions'] = json_decode($mergedJson, true) ?: [];
        $row[0]['source'] = 'database';

        return [
            'status' => 'success',
            'data' => $row[0],
        ];

    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    }
}

private function buildOwnerFallbackPermission(int $userID, int $tenantID): ?array {
    if ($userID <= 0 || $tenantID <= 0) {
        return null;
    }

    if (!$this->isTenantOwner($userID, $tenantID)) {
        return null;
    }

    $ownerLevel = projectPermissionLevel('owner');
    if ($ownerLevel === '') {
        return null;
    }

    $ownerPermissions = projectPermissionsForLevel($ownerLevel);
    $permissionsJson = json_encode($ownerPermissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($permissionsJson) || $permissionsJson === '') {
        $permissionsJson = '{}';
    }

    try {
        (new self([
            'tenant_id' => $tenantID,
            'user_id' => $userID,
            'permission_level' => $ownerLevel,
            'permissions_json' => $permissionsJson,
            'is_active' => 1,
        ]))->fromTable($this->table)->insert();
    } catch (Throwable $e) {
        // Fallback best-effort: if insert fails (duplicate/race), continue.
    }

    return [
        'permission_level' => $ownerLevel,
        'permissions_json' => $permissionsJson,
        'permissions' => $ownerPermissions,
        'source' => 'owner_fallback',
    ];
}

private function isTenantOwner(int $userID, int $tenantID): bool {
    if ($userID <= 0 || $tenantID <= 0) {
        return false;
    }

    if (!projectPermissionsUseTenancy()) {
        return false;
    }

    $tenantColumn = $this->resolveTenantColumn();
    $tenantTable = (string)TENANT_TABLE;

    $row = $this
        ->reset()
        ->queryTable($tenantTable)
        ->select('user_id')
        ->where($tenantColumn, '=', $tenantID)
        ->where('user_id', '=', $userID)
        ->limit(1)
        ->get();

    return !empty($row[0]['user_id']);
}

private function resolveTenantColumn(): string {
    $tenantKey = (string)TENANT;
    $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $tenantKey);
    $snake = strtolower((string)$snake);
    $snake = str_replace('_i_d', '_id', $snake);
    if (str_ends_with($snake, 'id') && !str_ends_with($snake, '_id')) {
        $snake = substr($snake, 0, -2) . '_id';
    }
    return $snake;
}

private function mergePermissionsWithTemplate(string $storedJson, string $permissionLevel): array {
    $template = projectPermissionsForLevel($permissionLevel);

    $current = json_decode($storedJson, true);
    if (!is_array($current)) {
        $current = [];
    }

    $didAddKeys = false;
    $merged = $this->mergeMissingKeys($current, $template, $didAddKeys);
    $mergedJson = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($mergedJson) || $mergedJson === '') {
        $mergedJson = '{}';
    }

    return [$mergedJson, $didAddKeys];
}

private function mergeMissingKeys(array $current, array $template, bool &$didAddKeys): array {
    foreach ($template as $key => $templateValue) {
        if (!array_key_exists($key, $current)) {
            $current[$key] = $templateValue;
            $didAddKeys = true;
            continue;
        }

        if (is_array($templateValue) && is_array($current[$key])) {
            $current[$key] = $this->mergeMissingKeys($current[$key], $templateValue, $didAddKeys);
        }
    }

    return $current;
}

public function getAllApiTokens() {
    return;
    try {
        /*$rows = $this
            ->reset()
            ->queryTable('bots')
            ->select('channels')
            ->get();

        if (empty($rows)) {
            return [
                'status' => 'error',
                'code'   => 'empty',
            ];
        }

        $map = [];

        foreach ($rows as $row) {
            $channelsRaw = $row['channels'] ?? null;

            if (is_string($channelsRaw) && $channelsRaw !== '') {
                $channels = json_decode($channelsRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                $token  = $channels['webchat']['token']  ?? '';
                $domain = $channels['webchat']['domain'] ?? '';

                if ($token && $domain) {
                    $host = $domain;
                    if (strpos($domain, '://') !== false) {
                        $host = parse_url($domain, PHP_URL_HOST) ?: $domain;
                    } else {
                        $maybe = parse_url('http://' . $domain, PHP_URL_HOST);
                        if ($maybe) $host = $maybe;
                    }
                    $map[$token] = $host;
                }
            }
        }

        if (empty($map)) {
            return [
                'status' => 'error',
                'code'   => 'empty',
            ];
        }

        return [
            'status' => 'success',
            'data'   => $map,
        ];*/

    } catch (Throwable $e) {
        return [
            'status'  => 'error',
            'message' => $e->getMessage(),
            'code'    => (string)$e->getCode(),
        ];
    }
}

/*fin de la clase*/
}
