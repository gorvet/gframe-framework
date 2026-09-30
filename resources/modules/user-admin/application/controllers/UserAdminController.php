<?php

use GFrame\Auth\RoleModel;
use GFrame\Auth\UserAdministrationService;
use GFrame\Auth\UserModel;

final class UserAdminController
{
    private UserAdministrationService $users;
    private RoleModel $roles;

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
        return $this->listResponse();
    }

    public function update(): array
    {
        $actorID = $this->actorID();
        $userID = (int)($_POST['user_id'] ?? 0);
        $operation = (string)($_POST['operation'] ?? '');

        if ($operation === 'role') {
            return $this->withMessage($this->users->assignRole($actorID, $userID, (int)($_POST['role_id'] ?? 0)));
        }
        if ($operation === 'status') {
            return $this->withMessage($this->users->setActive($actorID, $userID, (int)($_POST['active'] ?? 0) === 1));
        }

        return ['status' => 'error', 'code' => 'invalid_operation', 'message' => 'Operación no válida.'];
    }

    private function listResponse(): array
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

    private function actorID(): int
    {
        return (int)($_SESSION['auth']['id'] ?? $_SESSION['userID'] ?? 0);
    }

    private function withMessage(array $response): array
    {
        $messages = [
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
