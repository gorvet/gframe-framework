<?php

namespace GFrame\Modules\AuthUi\Controllers;

use Exception;

use GFrame\Auth\AuthModel;
use GFrame\Auth\SessionManager;
use GFrame\Auth\RoleModel;
use GFrame\Mail\MailService;

class AuthController
{
    use \HeartbeatChannelTrait;

    protected AuthModel $authModel;
    protected SessionManager $sessions;
    protected MailService $mail;
    protected RoleModel $roles;

    public function __construct(?AuthModel $auth = null, ?SessionManager $sessions = null, ?MailService $mail = null, ?RoleModel $roles = null)
    {
        $this->authModel = $auth ?? new AuthModel();
        $this->sessions = $sessions ?? new SessionManager();
        $this->mail = $mail ?? new MailService();
        $this->roles = $roles ?? new RoleModel();
    }

    public function login(): array
    {
        $email = trim((string)($_POST['login_email'] ?? ''));
        $password = (string)($_POST['login_password'] ?? '');
        if ($email === '' || $password === '') {
            return ['status' => 'error', 'code' => 'empty_field', 'message' => 'Complete el correo y la contraseña.'];
        }

        $response = $this->authModel->login($email, $password);
        if (($response['status'] ?? '') !== 'success') {
            return $this->withMessage($response);
        }

        $user = (array)($response['data']['user'] ?? []);
        $user = array_replace($user, $this->roles->authorizationForUser((int)($user['user_id'] ?? $user['id'] ?? 0)) ?? []);
        $mustChangePassword = !empty($response['data']['must_change_password']);
        try {
            $this->sessions->login([
                'id' => (int)($user['user_id'] ?? 0),
                'email' => (string)($user['email'] ?? ''),
                'role_id' => (int)($user['role_id'] ?? 0),
                'role' => (string)($user['role'] ?? ''),
                'permissions' => (array)($user['permissions'] ?? []),
                'role_version' => (int)($user['role_version'] ?? 1),
                'authorization_version' => (int)($user['authorization_version'] ?? 1),
                'bypass' => !empty($user['bypass']),
            ], ['must_change_password' => $mustChangePassword]);
        } catch (\Exception $exception) {
            error_log('[GFrame Auth UI] ' . $exception->getMessage());
            return $this->withMessage(['status' => 'error', 'code' => 'authentication_failed']);
        }

        $path = trim((string)config(
            $mustChangePassword ? 'auth.password_change_redirect' : 'auth.login_redirect',
            $mustChangePassword ? 'account' : 'admin'
        ), '/');
        if ($path === '' && !$mustChangePassword) $path = 'admin';
        $path = self::returnUrl($path) ?? ($mustChangePassword ? 'account' : 'admin');
        $returnUrl = $mustChangePassword ? null : self::returnUrl((string)($_POST['rd'] ?? ''));
        return [
            'status' => 'success',
            'code' => (string)($response['code'] ?? 'authenticated'),
            'message' => $mustChangePassword
                ? 'Debes cambiar tu contraseña antes de continuar.'
                : 'Sesión iniciada correctamente.',
            'redirect' => $returnUrl ?? $path,
            'data' => ['must_change_password' => $mustChangePassword],
        ];
    }

    public function logout(): array
    {
        $this->sessions->logout();
        return [
            'status' => 'success',
            'code' => 'logged_out',
            'redirect' => 'login',
        ];
    }

    public function heartbeatSessionChannel(array $payload = [], array $context = []): array
    {
        return $this->hbSuccess([], ['code' => 'alive']);
    }

    public function register(): array
    {
        $email = trim((string)($_POST['register_email'] ?? ''));
        $response = $this->authModel->registerAcount($email, (string)($_POST['register_password'] ?? ''));
        if (($response['status'] ?? '') !== 'success') {
            return $this->withMessage($response);
        }

        $token = (string)($response['data']['token'] ?? '');
        unset($response['data']['token'], $response['data']['user_id']);
        if (!$this->sendAccessMail(
            $email,
            'Verifica tu cuenta',
            'Verificar mi cuenta',
            '/login/verify?v=' . rawurlencode($token),
            'Gracias por registrarte. Confirma tu correo para activar la cuenta y empezar a usar ' . (defined('site_name') ? (string)site_name : 'GFrame') . '.'
        )) {
            return $this->withMessage(['status' => 'error', 'code' => 'mail_delivery_failed']);
        }

        return $this->withMessage($response);
    }

    public function verify(): array
    {
        return ['result' => $this->withMessage($this->authModel->validateAcount((string)($_GET['v'] ?? '')))];
    }

    public function validateAcount(): array
    {
        return $this->withMessage($this->authModel->validateAcount((string)($_POST['vtoken'] ?? '')));
    }

    public function resendVerification(): array
    {
        $email = trim((string)($_POST['login_email'] ?? ''));
        $response = $this->authModel->verifyAcount($email);
        $token = (string)($response['data']['token'] ?? '');
        unset($response['data']['token']);
        if (($response['status'] ?? '') === 'success' && $token !== '' && !$this->sendAccessMail(
            $email, 'Verifica tu cuenta', 'Verificar mi cuenta',
            '/login/verify?v=' . rawurlencode($token),
            'Confirma tu correo electrónico para activar la cuenta.'
        )) return $this->withMessage(['status' => 'error', 'code' => 'mail_delivery_failed']);
        return $this->withMessage($response);
    }

    protected static function returnUrl(string $path): ?string
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/[\\\\:\x00-\x20]/', $path)) return null;
        $decoded = rawurldecode(explode('?', explode('#', $path, 2)[0], 2)[0]);
        if (str_contains($decoded, '%') || str_starts_with($decoded, '/') || preg_match('/[\\\\:\x00-\x20]/', $decoded)) return null;
        foreach (explode('/', $decoded) as $segment) if ($segment === '.' || $segment === '..') return null;
        return $path;
    }

    public function recovery(): array
    {
        $email = trim((string)($_POST['recovery_email'] ?? ''));
        $response = $this->authModel->recoveryAcount($email);
        $token = (string)($response['data']['token'] ?? '');
        unset($response['data']['token']);

        if (($response['status'] ?? '') === 'success' && $token !== '' && !$this->sendAccessMail(
            $email,
            'Recupera tu cuenta',
            'Establecer una contraseña',
            '/login/resetpassword?rp=' . rawurlencode($token),
            'Recibimos una solicitud para restablecer tu contraseña.'
        )) {
            return $this->withMessage(['status' => 'error', 'code' => 'mail_delivery_failed']);
        }

        return $this->withMessage($response);
    }

    public function resetPassword(): array
    {
        return $this->withMessage($this->authModel->resetPassword(
            (string)($_POST['rpuser_token'] ?? $_POST['reset_token'] ?? ''),
            (string)($_POST['reset_password'] ?? '')
        ));
    }

    protected function sendAccessMail(string $email, string $subject, string $action, string $path, string $message): bool
    {
        if ($email === '') {
            return false;
        }

        $url = rtrim((string)site_url, '/') . $path;
        try {
            $name = $this->mailRecipientName($email);
            $siteName = defined('site_name') ? (string)site_name : 'GFrame';
            return ($this->mail->sendTemplateAsync($email, $subject . ' · ' . $siteName, 'mailTemplate', [
                'title' => $subject, 'h1' => $subject, 'p1' => $message,
                'greeting' => $name !== '' ? 'Hola, ' . $name . '.' : 'Hola.',
                'aHref' => $url, 'aText' => $action,
                'p2' => 'Si no solicitaste esta operación, ignora el mensaje.',
            ], ['recipient_name' => $name])['status'] ?? '') === 'success';
        } catch (Exception $exception) {
            error_log('[GFrame Auth UI] ' . $exception->getMessage());
            return false;
        }
    }

    protected function mailRecipientName(string $email): string
    {
        $user = $this->authModel->findByEmail($email);
        return trim((string)($user['name'] ?? '')) ?: (string)strtok($email, '@');
    }

    protected function withMessage(array $response): array
    {
        $messages = [
            'account_registered' => 'Revisa tu correo electrónico para activar la cuenta.',
            'account_verified' => 'La cuenta quedó verificada. Ya puedes acceder.',
            'verification_requested' => 'Si la cuenta necesita verificación, recibirás un correo con el enlace.',
            'already_verified' => 'La cuenta ya está verificada. Puedes acceder.',
            'verification_request_failed' => 'No se pudo solicitar la verificación.',
            'verification_failed' => 'No se pudo verificar la cuenta.',
            'recovery_requested' => 'Si la cuenta existe, recibirás un correo con las instrucciones.',
            'password_reset' => 'La contraseña fue restablecida.',
            'invalid_email' => 'Escribe un correo electrónico válido.',
            'invalid_password' => 'La contraseña no cumple los requisitos de seguridad.',
            'user_exists' => 'Ya existe una cuenta con ese correo electrónico.',
            'invalid_token' => 'El enlace no es válido o ya venció.',
            'mail_delivery_failed' => 'No se pudo enviar el correo electrónico.',
            'register_failed' => 'No se pudo crear la cuenta.',
            'recovery_failed' => 'No se pudo iniciar la recuperación.',
            'password_reset_failed' => 'No se pudo restablecer la contraseña.',
            'invalid_user' => 'El correo o la contraseña no son correctos.',
            'unverified_account' => 'La cuenta todavía no está verificada.',
            'suspended_account' => 'La cuenta está suspendida.',
            'authentication_failed' => 'No se pudo iniciar la sesión.',
        ];
        $code = (string)($response['code'] ?? '');
        if (!isset($response['message']) && isset($messages[$code])) {
            $response['message'] = $messages[$code];
        }
        return $response;
    }
}
