<?php
$userID = (int)($_SESSION['auth']['id'] ?? 0);
$access = (new \GFrame\Auth\RolePermissionService(new \GFrame\Auth\RoleModel()))->authorize($userID, 'users.view');
if (($access['status'] ?? '') === 'success'):
    $url = rtrim((string)site_url, '/') . '/admin/users';
?>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $url ? ' current' : '' ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-user" aria-hidden="true"></i><span>Usuarios</span></a></li>
<?php endif; ?>
