<?php

namespace GFrame\Install;

use GFrame\Auth\PasswordPolicy;
use GFrame\Auth\SystemRole;
use PDO;
use RuntimeException;

final class SuperadministratorInstaller
{
    public function __construct(private readonly PasswordPolicy $passwords = new PasswordPolicy())
    {
    }

    public function install(PDO $pdo, string $email, string $password): array
    {
        $validation = $this->validate($email, $password);
        if ($validation['status'] !== 'success') {
            return $validation;
        }
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            return ['status' => 'error', 'code' => 'application_already_installed'];
        }

        $role = $pdo->prepare('SELECT role_id FROM roles WHERE slug = :slug LIMIT 1');
        $role->execute(['slug' => SystemRole::SUPERADMINISTRATOR]);
        $roleID = (int)$role->fetchColumn();
        if ($roleID <= 0) {
            throw new RuntimeException('No se encontró el rol superadministrator.');
        }

        $now = date('Y-m-d H:i:s');
        $statement = $pdo->prepare(
            'INSERT INTO users (email, password, role_id, status, token, token_updated_at, password_changed_at, force_password_change) '
            . 'VALUES (:email, :password, :role_id, :status, :token, :token_updated_at, :password_changed_at, :force_password_change)'
        );
        $statement->execute([
            'email' => $email,
            'password' => $this->passwords->hash($password),
            'role_id' => $roleID,
            'status' => 'verify',
            'token' => '',
            'token_updated_at' => $now,
            'password_changed_at' => $now,
            'force_password_change' => 0,
        ]);

        return [
            'status' => 'success',
            'code' => 'superadministrator_created',
            'user_id' => (int)$pdo->lastInsertId(),
            'role_id' => $roleID,
        ];
    }

    public function validate(string $email, string $password): array
    {
        if (!filter_var(mb_strtolower(trim($email), 'UTF-8'), FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'code' => 'invalid_email'];
        }
        if (!$this->passwords->accepts($password)) {
            return ['status' => 'error', 'code' => 'invalid_password'];
        }
        return ['status' => 'success', 'code' => 'valid_superadministrator'];
    }

    public function removeCreated(PDO $pdo, int $userID): void
    {
        if ($userID <= 0) return;
        $statement = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id AND role_id = :role_id');
        $role = $pdo->query("SELECT role_id FROM roles WHERE slug = 'superadministrator' LIMIT 1")->fetchColumn();
        $statement->execute(['user_id' => $userID, 'role_id' => (int)$role]);
    }
}
