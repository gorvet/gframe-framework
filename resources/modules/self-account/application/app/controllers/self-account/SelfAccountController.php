<?php

namespace GFrame\Modules\SelfAccount\Controllers;

use GFrame\Auth\SelfAccountService;
use GFrame\Auth\SessionManager;

class SelfAccountController
{
    protected SelfAccountService $accounts;
    protected SessionManager $sessions;

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
            'data' => ['account' => $response['data'], 'deactivation_policy' => $this->hasCampaigns() ? (new \GFrame\Notifications\Campaigns\AccountDeactivationLifecycle())->policy() : null],
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
        $userID = $this->userID();
        $profile = $this->accounts->profile($userID);
        if (($profile['data']['status'] ?? '') === 'disabled') return ['status' => 'error', 'code' => 'account_already_deactivated', 'message' => 'La cuenta ya está desactivada.'];
        $response = $this->withMessage($this->accounts->deactivate(
            $userID,
            (string)($_POST['password'] ?? '')
        ));

        if (($response['status'] ?? '') === 'success') {
            if ($this->hasCampaigns()) {
                $lifecycle = (new \GFrame\Notifications\Campaigns\AccountDeactivationLifecycle())->register($userID, (string)site_url, true);
                $response['data']['deactivation'] = $lifecycle;
                if (($lifecycle['status'] ?? '') === 'error') $response['message'] = 'Cuenta desactivada. No se pudo completar la programación de sus avisos; no se eliminará sin aviso previo.';
            }
            $this->sessions->logout();
            $response['redirect'] = 'login';
        }

        return $response;
    }

    protected function hasCampaigns(): bool
    {
        return \GFrame\Modules\ModuleRuntime::has('notification-campaigns');
    }

    protected function userID(): int
    {
        return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0);
    }

    protected function withMessage(array $response): array
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
