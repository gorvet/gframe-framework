<?php
$item = (array)($data['data']['notification'] ?? []);
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$action = trim((string)($item['action_url'] ?? ''));
$safeAction = preg_match('~^https?://[^\s]+$|^/(?!/)[^\s]*$~i', $action) === 1;
?>
<div class="col-12" data-notification-detail="<?= (int)($item['notification_id'] ?? 0) ?>">
    <div class="pagetitle"><h1>Notificación</h1></div>
    <div class="card"><div class="card-body">
        <?php if ($item === []): ?><p class="mb-0">La notificación no está disponible.</p>
        <?php else: ?>
        <h2 class="fs-5"><?= $escape($item['title']) ?></h2>
        <small class="text-body-secondary"><?= $escape($item['created_at']) ?></small>
        <p class="mt-3 notification-detail-message"><?= nl2br($escape($item['message'])) ?></p>
        <?php if ($safeAction): ?><a class="btn btn-primary" href="<?= $escape($action) ?>">Ver contenido</a><?php endif; ?>
        <?php endif; ?>
    </div></div>
</div>
