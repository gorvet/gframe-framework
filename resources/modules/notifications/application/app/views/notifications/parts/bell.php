<li class="nav-item dropdown gframe-notifications">
    <button class="nav-link btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notificaciones" aria-controls="notification-dropdown">
        <span class="gframe-notification-icon"><i class="gicon-bell" aria-hidden="true"></i><span class="badge bg-danger" data-notifications-count hidden>0</span></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end gframe-notifications-menu" id="notification-dropdown">
        <div class="border-bottom px-3 py-2"><strong>Notificaciones</strong></div>
        <div class="nav nav-pills gap-2 px-3 py-2" aria-label="Filtrar notificaciones"><button class="nav-link active" type="button" data-notifications-inbox-filter="all" aria-pressed="true">Todas</button><button class="nav-link" type="button" data-notifications-inbox-filter="unread" aria-pressed="false">No leídas</button></div>
        <div data-notifications-inbox aria-live="polite"></div>
        <a class="d-block text-center border-top px-3 py-2" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/notifications', ENT_QUOTES, 'UTF-8') ?>">Ver todas las notificaciones</a>
    </div>
</li>
