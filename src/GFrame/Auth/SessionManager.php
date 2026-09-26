<?php

namespace GFrame\Auth;

final class SessionManager
{
    public function login(array $identity, array $projectSession = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['auth'] = $this->normalizeIdentity([
            'id' => (int)($identity['id'] ?? 0),
            'email' => (string)($identity['email'] ?? ''),
            'name' => (string)($identity['name'] ?? ''),
            'role' => (string)($identity['role'] ?? ''),
            'is_super_admin' => $identity['is_super_admin'] ?? false,
        ]);

        foreach ($projectSession as $key => $value) {
            if (is_string($key) && $key !== '') {
                $_SESSION[$key] = $value;
            }
        }

        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        $_SESSION['lastActivity'] = time();
        $_SESSION['csrfTimestamp'] = time();
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function updateIdentity(array $identity, array $projectSession = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $currentIdentity = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $_SESSION['auth'] = $this->normalizeIdentity(array_replace($currentIdentity, $identity));

        foreach ($projectSession as $key => $value) {
            if (is_string($key) && $key !== '') {
                $_SESSION[$key] = $value;
            }
        }
    }

    private function normalizeIdentity(array $identity): array
    {
        return [
            'id' => (int)($identity['id'] ?? 0),
            'email' => trim((string)($identity['email'] ?? '')),
            'name' => trim((string)($identity['name'] ?? '')),
            'role' => mb_strtolower(trim((string)($identity['role'] ?? '')), 'UTF-8'),
            'is_super_admin' => $this->normalizeBoolean($identity['is_super_admin'] ?? false),
        ];
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int)$value === 1;
        }

        return in_array(mb_strtolower(trim((string)$value), 'UTF-8'), ['1', 'true', 'yes', 'on'], true);
    }
}
