<?php
$campaigns = (array)($data['data'] ?? []);
$meta = (array)($data['meta'] ?? []);
$canManage = !empty($data['can_manage']);
$base = rtrim((string)site_url, '/');
?>
<div class="col-12" data-campaigns-root data-list-url="<?= htmlspecialchars($base . '/ajax/admin/notifications/campaigns/list', ENT_QUOTES, 'UTF-8') ?>" data-action-url="<?= htmlspecialchars($base . '/ajax/admin/notifications/campaigns/action', ENT_QUOTES, 'UTF-8') ?>" data-page="<?= (int)($meta['page'] ?? 1) ?>">
    <div class="pagetitle">
        <div class="d-flex align-items-center flex-wrap">
            <div class="d-inline-block me-2">
                <h1 class="me-3">Campañas</h1>
                <span class="d-block">Envía ahora o programa notificaciones</span>
            </div>
            <?php if ($canManage): ?>
            <div class="d-inline-block">
                <a class="btn btn-primary ms-0 ms-md-3 mt-3 mt-md-0" href="<?= htmlspecialchars($base . '/admin/notifications/campaigns/new', ENT_QUOTES, 'UTF-8') ?>">Nueva campaña</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card mb-4"><div class="card-body">
        <form class="row g-3 align-items-end" data-campaign-filters method="get">
            <div class="col-12 col-md-4"><label class="form-label" for="campaign-status">Estado</label><select class="form-select" id="campaign-status" name="status"><option value="all">Todos los estados</option><?php foreach (['draft' => 'Borradores', 'scheduled' => 'Programadas', 'running' => 'En proceso', 'paused' => 'Pausadas', 'completed' => 'Completadas', 'failed' => 'Con errores', 'cancelled' => 'Canceladas'] as $value => $label): ?><option value="<?= $value ?>"<?= ($meta['status'] ?? '') === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        </form>
    </div></div>
    <div data-campaign-list><?php include \GFrame\Modules\ModuleRuntime::file('views', 'notification-campaigns/_list.php', 'notification-campaigns'); ?></div>
</div>
