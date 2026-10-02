<?php
$menuSection = 'user';
$userID = (int)($_SESSION['auth']['id'] ?? 0);
$access = (new \GFrame\Auth\RolePermissionService(new \GFrame\Auth\RoleModel()))->authorize($userID, 'media.view');
if (($access['status'] ?? '') === 'success'):
    $url = rtrim((string)site_url, '/') . '/admin/media';
?>
<li class="nav-heading">Multimedia</li>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $url ? ' current' : '' ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-image" aria-hidden="true"></i><span>Biblioteca multimedia</span></a></li>
<?php endif; ?>
