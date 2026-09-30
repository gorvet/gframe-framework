<li class="nav-item dropdown">
    <button class="nav-link btn position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
        <i class="gicon-bell" aria-hidden="true"></i><span class="badge bg-primary position-absolute top-0 start-100 translate-middle" data-notifications-count hidden>0</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-3" style="width: min(24rem, calc(100vw - 1rem))">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><strong>Notificaciones</strong><button class="btn btn-link btn-sm" type="button" data-notifications-mark-all>Marcar todas</button></div>
        <div data-notifications-inbox aria-live="polite"></div>
        <a class="d-block text-center pt-2" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/notifications', ENT_QUOTES, 'UTF-8') ?>">Ver todas las notificaciones</a>
    </div>
</li>
