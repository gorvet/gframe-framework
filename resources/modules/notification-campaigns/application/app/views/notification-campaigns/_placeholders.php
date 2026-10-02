<div class="col-12">
    <div class="row g-2">
        <div class="col-12 d-flex flex-wrap gap-2">
            <?php foreach (['user_name' => 'Nombre', 'user_email' => 'Correo', 'user_role' => 'Rol', 'user_status' => 'Estado', 'user_id' => 'ID del usuario', 'site_url' => 'Sitio', 'dashboard_url' => 'Escritorio', 'notifications_url' => 'Notificaciones'] as $key => $label): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary js-notif-placeholder" data-placeholder="{{<?= $key ?>}}" title="{{<?= $key ?>}}"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
