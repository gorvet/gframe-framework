<?php
// La aplicación define aquí sus secciones y enlaces según sus permisos.
$accountUrl = rtrim((string)site_url, '/') . '/account';
$dashboardUrl = rtrim((string)site_url, '/') . '/admin';
?>
<li class="nav-heading">Administración</li>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $dashboardUrl ? ' current' : '' ?>" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-dashboard" aria-hidden="true"></i><span>Escritorio</span></a></li>
<li class="nav-heading">Mi cuenta</li>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $accountUrl ? ' current' : '' ?>" href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-user" aria-hidden="true"></i><span>Cuenta y seguridad</span></a></li>
