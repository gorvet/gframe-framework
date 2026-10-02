<?php
$item = (array)($response['data']['notification'] ?? []);
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$action = trim((string)($item['action_url'] ?? ''));
$safeAction = preg_match('~^https?://[^\s]+$|^/(?!/)[^\s]*$~i', $action) === 1;
?>
<div class="modal fade" id="notification-detail-modal" tabindex="-1" aria-labelledby="notification-detail-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="notification-detail-title"><?= $escape($item['title'] ?? '') ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
    <div class="modal-body"><small class="text-body-secondary"><?= $escape($item['created_at'] ?? '') ?></small><p class="mt-3 mb-0 notification-detail-message"><?= nl2br($escape($item['message'] ?? '')) ?></p></div>
    <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cerrar</button><?php if ($safeAction): ?><a class="btn btn-primary" href="<?= $escape($action) ?>">Ver contenido</a><?php endif; ?></div>
</div></div></div>
