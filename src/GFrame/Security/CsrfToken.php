<?php

namespace GFrame\Security;

final class CsrfToken
{
    public static function ensure(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        if (!is_string($_SESSION['csrfToken'] ?? null) || $_SESSION['csrfToken'] === '' || !is_int($_SESSION['csrfTimestamp'] ?? null)) {
            self::rotate();
        }
        if (!headers_sent()) header('Cache-Control: private, no-store');
    }

    public static function rotate(): void
    {
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        $_SESSION['csrfTimestamp'] = time();
    }

    /** No inicia sesiones: una ruta noSession no puede emitir tokens. */
    public static function fields(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return '';
        self::ensure();
        return '<input type="hidden" name="csrfToken" value="' . htmlspecialchars($_SESSION['csrfToken'], ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="csrfTimestamp" value="' . (int)$_SESSION['csrfTimestamp'] . '">';
    }

    public static function validate(mixed $token, mixed $timestamp): array
    {
        if (!is_string($token) || !is_string($_SESSION['csrfToken'] ?? null)
            || (!is_string($timestamp) && !is_int($timestamp)) || !ctype_digit((string)$timestamp)
            || !isset($_SESSION['csrfTimestamp'])) {
            return ['status' => 'unauthorized', 'code' => 'fail_csrf', 'message' => 'Falta un token CSRF válido.'];
        }
        if (hash_equals($_SESSION['csrfToken'], $token) && (string)$timestamp === (string)$_SESSION['csrfTimestamp']) {
            // Para usuarios autenticados, el token caduca con la sesión (logout o
            // inactividad); no se le aplica un temporizador propio que forzaría un
            // 403 a mitad del trabajo. Solo las sesiones de invitado admiten una
            // caducidad acotada, pensada para formularios públicos.
            if (self::isGuestSession()) {
                $maxAge = (int)\GFrame\Config\ConfigRepository::get('security.csrf_guest_max_age', 0);
                if ($maxAge > 0 && (time() - (int)$_SESSION['csrfTimestamp']) > $maxAge) {
                    self::rotate();
                    return ['status' => 'unauthorized', 'code' => 'to_reload', 'message' => 'El formulario caducó. Recárgalo e inténtalo de nuevo.'];
                }
            }
            return ['status' => 'success'];
        }
        return ['status' => 'unauthorized', 'code' => (int)$timestamp < (int)$_SESSION['csrfTimestamp'] ? 'to_reload' : 'fail_csrf', 'message' => 'Recarga el formulario e inténtalo de nuevo.'];
    }

    /** Una sesión sin identidad autenticada es de invitado (formularios públicos). */
    private static function isGuestSession(): bool
    {
        $auth = $_SESSION['auth'] ?? null;
        return !is_array($auth) || (int)($auth['id'] ?? 0) <= 0;
    }
}
