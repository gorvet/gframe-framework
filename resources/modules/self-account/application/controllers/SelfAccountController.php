<?php

use GFrame\Auth\SelfAccountService;
use GFrame\Auth\SessionManager;

final class SelfAccountController
{
    private SelfAccountService $accounts;
    private SessionManager $sessions;

    public function __construct(?SelfAccountService $accounts = null, ?SessionManager $sessions = null)
    {
        $this->accounts = $accounts ?? new SelfAccountService();
        $this->sessions = $sessions ?? new SessionManager();
    }

    public function index(): array
    {
        $response = $this->accounts->profile($this->userID());
        if (($response['status'] ?? '') !== 'success') {
            return $this->withMessage($response);
        }

        return $this->withMessage([
            'status' => 'success',
            'code' => 'account_loaded',
            'data' => ['account' => $response['data']],
        ]);
    }

    public function changePassword(): array
    {
        $response = $this->withMessage($this->accounts->changePassword(
            $this->userID(),
            (string)($_POST['current_password'] ?? ''),
            (string)($_POST['new_password'] ?? ''),
            (string)($_POST['new_password_confirmation'] ?? '')
        ));

        if (($response['status'] ?? '') === 'success') {
            unset($_SESSION['must_change_password']);
        }

        return $response;
    }

    public function deactivate(): array
    {
        $response = $this->withMessage($this->accounts->deactivate(
            $this->userID(),
            (string)($_POST['password'] ?? '')
        ));

        if (($response['status'] ?? '') === 'success') {
            $this->sessions->logout();
            $response['redirect'] = rtrim((string)site_url, '/') . '/login';
        }

        return $response;
    }

    private function userID(): int
    {
        return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0);
    }

    private function withMessage(array $response): array
    {
        $messages = [
            'account_loaded' => 'Cuenta cargada correctamente.',
            'password_updated' => 'Contraseña actualizada correctamente.',
            'account_deactivated' => 'Tu cuenta fue desactivada correctamente.',
            'empty_field' => 'Completa todos los campos requeridos.',
            'invalid_password' => 'La nueva contraseña no cumple los requisitos de seguridad.',
            'invalid_current_password' => 'La contraseña actual no es correcta.',
            'password_mismatch' => 'Las contraseñas nuevas no coinciden.',
            'protected_account' => 'La cuenta del superadministrador no puede desactivarse.',
            'not_found' => 'Cuenta no encontrada.',
            'account_load_failed' => 'No se pudo cargar la cuenta.',
            'password_update_failed' => 'No se pudo actualizar la contraseña.',
            'account_deactivation_failed' => 'No se pudo desactivar la cuenta.',
        ];

        $code = (string)($response['code'] ?? '');
        if (!isset($response['message']) && isset($messages[$code])) {
            $response['message'] = $messages[$code];
        }

        return $response;
    }
}
