<?php
$history = (array)($data['data']['history'] ?? []);
$items = (array)($history['data']['items'] ?? []);
$meta = (array)($history['meta'] ?? []);
?>
<div class="container py-4 py-lg-5" data-notifications-history>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="h2 mb-1">Todas las notificaciones</h1><p class="text-body-secondary mb-0">Consulta tus avisos anteriores.</p></div>
        <button class="btn btn-outline-primary" type="button" data-notifications-mark-all>Marcar todas como leídas</button>
    </div>
    <form class="mb-3" data-notifications-filter><label class="form-label" for="notifications-filter">Mostrar</label><select class="form-select w-auto" id="notifications-filter" name="filter"><option value="all">Todas</option><option value="unread" <?= ($_GET['filter'] ?? '') === 'unread' ? 'selected' : '' ?>>No leídas</option></select></form>
    <div data-notifications-results><?php include __DIR__ . '/_history.php'; ?></div>
</div>
