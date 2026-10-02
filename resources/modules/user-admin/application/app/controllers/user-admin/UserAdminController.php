<?php

namespace GFrame\Modules\UserAdmin\Controllers;

use GFrame\Auth\RoleModel;
use GFrame\Auth\UserAdministrationService;
use GFrame\Auth\UserModel;

class UserAdminController
{
    protected UserAdministrationService $users;
    protected RoleModel $roles;

    public function __construct(?UserAdministrationService $users = null, ?RoleModel $roles = null)
    {
        $this->roles = $roles ?? new RoleModel();
        $this->users = $users ?? new UserAdministrationService(new UserModel(), $this->roles);
    }

    public function index(): array
    {
        $actorID = $this->actorID();
        $users = $this->listResponse();
        if (($users['status'] ?? '') !== 'success') {
            return $users;
        }
        $roles = $this->users->assignableRoles($actorID);
        if (($roles['status'] ?? '') !== 'success') {
            return $this->withMessage($roles);
        }
        $capabilities = $this->users->capabilities($actorID);

        return $this->withMessage([
            'status' => 'success',
            'code' => 'users_loaded',
            'data' => [
                'users' => $users,
                'roles' => (array)($roles['data'] ?? []),
                'can_manage' => !empty($capabilities['data']['manage']),
            ],
        ]);
    }

    public function list(): array
    {
        $page = $this->index();
        if (($page['status'] ?? '') !== 'success') {
            return $page;
        }
        $response = (array)$page['data']['users'];
        $roles = (array)$page['data']['roles'];
        $canManage = !empty($page['data']['can_manage']);
        ob_start();
        try {
            $partial = \GFrame\Modules\ModuleRuntime::file('views', 'user-admin/_userList.php', 'user-admin');
            if ($partial === null) throw new \RuntimeException('No se encontró el parcial del listado de usuarios.');
            include $partial;
            $response['html'] = (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
        return $response;
    }

    public function update(): array
    {
        $actorID = $this->actorID();
        $userID = (int)($_POST['user_id'] ?? 0);
        $operation = (string)($_POST['operation'] ?? '');

        if ($operation === 'role') {
            return $this->withMessage($this->users->assignRole($actorID, $userID, (int)($_POST['role_id'] ?? 0)));
        }
        if (in_array($operation, ['verify', 'suspend', 'restore', 'delete'], true)) {
            if ($operation === 'delete' && (string)($_POST['confirmed'] ?? '') !== '1') {
                return ['status' => 'error', 'code' => 'confirmation_required', 'message' => 'Confirma la eliminación de la cuenta.'];
            }
            $response = $this->users->moderate($actorID, $userID, $operation);
            if (($response['code'] ?? '') === 'user_suspended' && ($response['status'] ?? '') === 'success'
                && defined('ABSPATH') && is_file(ABSPATH . 'app/views/admin/notifications/campaigns/automatic.php')) {
                $notice = \GFrame\Notifications\Campaigns\AutomaticCampaignDispatcher::emit('account_suspended', $userID, bin2hex(random_bytes(16)), ['site_url' => (string)site_url]);
                $response['data']['automatic_notice'] = $notice;
                if (($notice['status'] ?? '') === 'error') $response['message'] = 'Cuenta suspendida; no se pudo añadir el aviso automático a la cola.';
            }
            return $this->withMessage($response);
        }

        return ['status' => 'error', 'code' => 'invalid_operation', 'message' => 'Operación no válida.'];
    }

    protected function listResponse(): array
    {
        return $this->withMessage($this->users->paginate(
            $this->actorID(),
            max(1, (int)($_REQUEST['page'] ?? 1)),
            20,
            trim((string)($_REQUEST['search'] ?? '')),
            trim((string)($_REQUEST['role'] ?? '')),
            trim((string)($_REQUEST['status'] ?? ''))
        ));
    }

    protected function actorID(): int
    {
        return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0);
    }

    protected function withMessage(array $response): array
    {
        $messages = [
            'user_verified' => 'Cuenta verificada.',
            'user_suspended' => 'Cuenta suspendida.',
            'user_restored' => 'Acceso restablecido.',
            'user_deleted' => 'Cuenta eliminada.',
            'invalid_status_transition' => 'Esta acción no está disponible para el estado actual de la cuenta.',
            'moderation_not_supported' => 'El repositorio de la aplicación no implementa estas acciones.',
            'user_moderation_failed' => 'No se pudo completar la acción. La cuenta puede tener registros relacionados que impiden eliminarla.',
            'user_activated' => 'Usuario activado.',
            'user_deactivated' => 'Usuario desactivado.',
            'role_assigned' => 'Rol actualizado.',
            'protected_user' => 'La cuenta del superadministrador está protegida.',
            'invalid_role_assignment' => 'No se puede asignar ese rol.',
            'forbidden' => 'No tienes permiso para administrar usuarios.',
            'users_loaded' => 'Usuarios cargados correctamente.',
            'user_not_found' => 'Usuario no encontrado.',
            'self_protection' => 'No puedes modificar tu propia cuenta desde esta pantalla.',
            'users_list_failed' => 'No se pudo cargar la lista de usuarios.',
            'roles_list_failed' => 'No se pudieron cargar los roles.',
            'user_status_update_failed' => 'No se pudo actualizar el estado del usuario.',
            'role_assignment_failed' => 'No se pudo actualizar el rol.',
        ];
        $code = (string)($response['code'] ?? '');
        if (!isset($response['message']) && isset($messages[$code])) {
            $response['message'] = $messages[$code];
        }
        return $response;
    }
}
