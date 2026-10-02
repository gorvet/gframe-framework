<?php
$userID = (int)($_SESSION['auth']['id'] ?? 0);
$access = (new \GFrame\Auth\RolePermissionService(new \GFrame\Auth\RoleModel()))->authorize($userID, 'notifications.campaigns.view');
if (($access['status'] ?? '') === 'success'):
    $url = rtrim((string)site_url, '/') . '/admin/notifications/campaigns';
?>
<li class="nav-heading">Campañas</li>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $url ? ' current' : '' ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-mail" aria-hidden="true"></i><span>Campañas</span></a></li>
<?php if (((new \GFrame\Auth\RolePermissionService(new \GFrame\Auth\RoleModel()))->authorize($userID, 'notifications.campaigns.manage')['status'] ?? '') === 'success'): $newUrl = $url . '/new'; ?>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $newUrl ? ' current' : '' ?>" href="<?= htmlspecialchars($newUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-plus" aria-hidden="true"></i><span>Nueva campaña</span></a></li>
<?php $automaticUrl = $url . '/automatic'; ?>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $automaticUrl ? ' current' : '' ?>" href="<?= htmlspecialchars($automaticUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-mail" aria-hidden="true"></i><span>Campañas automáticas</span></a></li>
<?php $historyUrl = $automaticUrl . '/history'; ?>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $historyUrl ? ' current' : '' ?>" href="<?= htmlspecialchars($historyUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-mail" aria-hidden="true"></i><span>Historial de envíos</span></a></li>
<?php endif; ?>
<?php endif; ?>
