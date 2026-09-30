<?php

namespace GFrame\Install;

use PDO;
use RuntimeException;
use GFrame\Session\SessionRuntime;

final class PermissionTemplateSynchronizer
{
    public function sync(PDO $database, string $projectRoot, bool $dryRun = false): array
    {
        $path = rtrim($projectRoot, '\\/') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Permissions.php';
        if (!is_file($path)) return ['roles' => 0, 'granted' => 0, 'revoked' => 0];
        $templates = require $path;
        if (!is_array($templates)) throw new RuntimeException('config/Permissions.php debe retornar un arreglo.');
        $result = ['roles' => 0, 'granted' => 0, 'revoked' => 0];
        $changedRoles = [];
        $ownsTransaction = !$dryRun && !$database->inTransaction();
        if ($ownsTransaction) $database->beginTransaction();
        try {
            foreach ($templates as $roleSlug => $definition) {
                $roleSlug = $this->slug((string)$roleSlug);
                if ($roleSlug === '' || !is_array($definition)) throw new RuntimeException('La plantilla de permisos contiene un rol inválido.');
                $defined = $this->flatten($definition);
                $statement = $database->prepare('SELECT role_id, permissions_json FROM roles WHERE slug = ? LIMIT 1');
                $statement->execute([$roleSlug]);
                $role = $statement->fetch(PDO::FETCH_ASSOC);
                $current = $role ? $this->decode((string)($role['permissions_json'] ?? '')) : [];
                $next = array_replace($current, $defined);
                foreach ($defined as $slug => $allowed) {
                    if (!array_key_exists($slug, $current) || $current[$slug] !== $allowed) $result[$allowed ? 'granted' : 'revoked']++;
                }
                if (!$role) {
                    $result['roles']++;
                    if (!$dryRun) {
                        $database->prepare('INSERT INTO roles (name, slug, is_system, permissions_json) VALUES (?, ?, 0, ?)')->execute([$this->label($roleSlug), $roleSlug, $this->encode($next)]);
                        $changedRoles[(int)$database->lastInsertId()] = 1;
                    }
                } elseif ($next !== $current && !$dryRun) {
                    $database->prepare('UPDATE roles SET permissions_json = ?, security_version = security_version + 1 WHERE role_id = ?')->execute([$this->encode($next), (int)$role['role_id']]);
                    $changedRoles[(int)$role['role_id']] = (int)$database->query('SELECT security_version FROM roles WHERE role_id = ' . (int)$role['role_id'])->fetchColumn();
                }
            }
            if ($ownsTransaction) $database->commit();
            if (!$dryRun) foreach ($changedRoles as $roleID => $version) SessionRuntime::publishRoleVersion($roleID, $version);
            return $result;
        } catch (\Exception $exception) {
            if ($ownsTransaction && $database->inTransaction()) $database->rollBack();
            throw $exception;
        }
    }

    private function flatten(array $definition, string $prefix = ''): array
    {
        $permissions = [];
        foreach ($definition as $key => $value) {
            $segment = $this->slug((string)$key);
            if ($segment === '') throw new RuntimeException('La plantilla contiene un permiso inválido.');
            $slug = $prefix === '' ? $segment : $prefix . '.' . $segment;
            if (is_array($value)) $permissions = array_replace($permissions, $this->flatten($value, $slug));
            elseif (is_bool($value)) $permissions[$slug] = $value;
            else throw new RuntimeException("El permiso {$slug} debe ser booleano o contener acciones.");
        }
        return $permissions;
    }

    private function decode(string $json): array { $value = json_decode($json, true); return is_array($value) ? $value : []; }
    private function encode(array $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'; }
    private function slug(string $value): string { $value = mb_strtolower(trim($value), 'UTF-8'); return preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value) === 1 ? $value : ''; }
    private function label(string $slug): string { return ucfirst(str_replace(['.', '_', '-'], ' ', $slug)); }
}
