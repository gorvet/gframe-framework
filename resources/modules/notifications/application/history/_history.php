<div class="card"><div class="card-body">
    <?php include ABSPATH . 'app/views/components/notifications/_inbox.php'; ?>
</div></div>
<?php $page = max(1, (int)($meta['page'] ?? 1)); $pages = max(1, (int)($meta['total_pages'] ?? 1)); ?>
<div class="d-flex align-items-center justify-content-between gap-3 mt-3" data-notifications-pagination>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-notifications-page="<?= $page - 1 ?>" <?= $page <= 1 ? 'disabled' : '' ?>>Anterior</button>
    <span class="small text-body-secondary">Página <?= $page ?> de <?= $pages ?></span>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-notifications-page="<?= $page + 1 ?>" <?= $page >= $pages ? 'disabled' : '' ?>>Siguiente</button>
</div>
