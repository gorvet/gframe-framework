<?php
$menuSection = 'user';
$userID = (int)($_SESSION['auth']['id'] ?? 0);
if ($userID > 0):
    $url = rtrim((string)site_url, '/') . '/notifications';
?>
<li class="nav-heading">Notificaciones</li>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $url ? ' current' : '' ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-bell" aria-hidden="true"></i><span>Notificaciones</span></a></li>
<?php endif; ?>
