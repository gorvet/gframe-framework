<?php

function projectPermissionsConfig(): array {
  static $permissions = null;

  if ($permissions !== null) {
    return $permissions;
  }

  $path = realpath(ABSPATH . 'config/Permissions.php');
  if ($path === false || !file_exists($path)) {
    $permissions = [];
    return $permissions;
  }

  $loaded = require $path;
  $permissions = is_array($loaded) ? $loaded : [];

  return $permissions;
}

function projectPermissionsUseTenancy(): bool {
  $hasTenantKey = defined('TENANT') && trim((string)TENANT) !== '';
  $hasTenantTable = defined('TENANT_TABLE') && trim((string)TENANT_TABLE) !== '';

  if ($hasTenantKey !== $hasTenantTable) {
    throw new RuntimeException('La configuración de permisos requiere definir TENANT y TENANT_TABLE juntos.');
  }

  return $hasTenantKey && $hasTenantTable;
}

function projectPermissionLevel(string $level, string $fallback = ''): string {
  $permissions = projectPermissionsConfig();
  $normalized = strtolower(trim($level));

  if ($normalized !== '' && array_key_exists($normalized, $permissions)) {
    return $normalized;
  }

  $normalizedFallback = strtolower(trim($fallback));
  if ($normalizedFallback !== '' && array_key_exists($normalizedFallback, $permissions)) {
    return $normalizedFallback;
  }

  return '';
}

function projectPermissionsForLevel(string $level, string $fallback = ''): array {
  $permissions = projectPermissionsConfig();
  $normalized = projectPermissionLevel($level, $fallback);
  $resolved = $permissions[$normalized] ?? [];

  return is_array($resolved) ? $resolved : [];
}

function projectPermissionGranted(array $permissions, string $module, string $action): bool {
  $module = strtolower(trim($module));
  $action = strtolower(trim($action));
  if ($module === '' || $action === '') {
    return false;
  }

  $modulePermissions = $permissions[$module] ?? $permissions['*'] ?? null;
  if (!is_array($modulePermissions)) {
    return false;
  }

  return ($modulePermissions[$action] ?? $modulePermissions['*'] ?? false) === true;
}
