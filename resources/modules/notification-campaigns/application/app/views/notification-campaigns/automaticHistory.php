<?php $base = rtrim((string)site_url, '/'); $meta = (array)($data['meta'] ?? []); ?>
<div class="col-12" data-campaigns-root data-list-url="<?= htmlspecialchars($base . '/ajax/admin/notifications/campaigns/automatic/history', ENT_QUOTES, 'UTF-8') ?>" data-page="<?= (int)($meta['page'] ?? 1) ?>">
    <div class="pagetitle"><h1>Historial de campañas automáticas</h1></div>
    <div data-campaign-list><?= $data['html'] ?? '' ?></div>
</div>
