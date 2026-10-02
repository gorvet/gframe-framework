<?php
// La aplicación define aquí sus secciones y enlaces según sus permisos.
$dashboardUrl = rtrim((string)site_url, '/') . '/admin';
?>
<li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $dashboardUrl ? ' current' : '' ?>" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-dashboard" aria-hidden="true"></i><span>Escritorio</span></a></li>
