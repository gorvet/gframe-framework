<?php

namespace GFrame\Auth;

use Exception;
use GFrame\Session\ActiveSessionRegistry;
use GFrame\Session\SessionRuntime;

final class SessionManager
{
    private ?ActiveSessionRegistry $sessions;

    public function __construct(?ActiveSessionRegistry $sessions = null)
    {
        $this->sessions = $sessions ?? SessionRuntime::registry();
    }

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
            'role_id' => (int)($identity['role_id'] ?? 0),
            'role' => (string)($identity['role'] ?? ''),
            'permissions' => (array)($identity['permissions'] ?? []),
            'role_version' => (int)($identity['role_version'] ?? 1),
            'authorization_version' => (int)($identity['authorization_version'] ?? 1),
            'bypass' => !empty($identity['bypass']),
        ]);

        foreach ($projectSession as $key => $value) {
            if (is_string($key) && $key !== '') {
                $_SESSION[$key] = $value;
            }
        }

        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        $_SESSION['lastActivity'] = time();
        $_SESSION['csrfTimestamp'] = time();

        $userID = (int)($_SESSION['auth']['id'] ?? 0);
        if ($this->sessions !== null && $userID > 0) {
            try {
                $this->sessions->register($userID, session_id(), [
                    'role_id' => (int)($_SESSION['auth']['role_id'] ?? 0),
                    'role_version' => (int)($_SESSION['auth']['role_version'] ?? 1),
                    'authorization_version' => (int)($_SESSION['auth']['authorization_version'] ?? 1),
                ]);
            } catch (Exception $exception) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                throw $exception;
            }
        }
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userID = (int)($_SESSION['auth']['id'] ?? 0);
        $sessionID = session_id();
        if ($this->sessions !== null && $userID > 0 && $sessionID !== '') {
            $this->sessions->unregister($userID, $sessionID);
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
        $roleID = (int)($identity['role_id'] ?? 0);
        $role = mb_strtolower(trim((string)($identity['role'] ?? '')), 'UTF-8');

        return [
            'id' => (int)($identity['id'] ?? 0),
            'email' => trim((string)($identity['email'] ?? '')),
            'name' => trim((string)($identity['name'] ?? '')),
            'role_id' => $roleID,
            'role' => $role,
            'permissions' => array_values(array_unique(array_filter(array_map('strval', (array)($identity['permissions'] ?? []))))),
            'role_version' => max(1, (int)($identity['role_version'] ?? 1)),
            'authorization_version' => max(1, (int)($identity['authorization_version'] ?? 1)),
            'bypass' => !empty($identity['bypass']),
        ];
    }
}
