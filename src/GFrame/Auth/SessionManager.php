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
        $_SESSION['auth'] = [
            'id' => (int)($identity['id'] ?? 0),
            'email' => (string)($identity['email'] ?? ''),
            'name' => (string)($identity['name'] ?? ''),
            'role' => (string)($identity['role'] ?? ''),
        ];

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
}
