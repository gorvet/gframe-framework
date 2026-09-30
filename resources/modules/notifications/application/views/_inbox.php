<?php if ($items === []): ?><p class="text-body-secondary text-center mb-0" data-notifications-empty>No tienes notificaciones.</p><?php endif; ?>
<?php foreach ($items as $item): ?>
<?php $action = trim((string)($item['action_url'] ?? '')); $safeAction = preg_match('~^https?://[^\s]+$|^/(?!/)[^\s]*$~i', $action) === 1; ?>
<article class="notification-inbox-item<?= empty($item['is_read']) ? ' is-unread' : '' ?>" data-notification-id="<?= (int)$item['notification_id'] ?>">
    <div class="flex-grow-1">
        <strong class="d-block"><?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?></strong>
        <p class="mb-1"><?= nl2br(htmlspecialchars((string)$item['message'], ENT_QUOTES, 'UTF-8')) ?></p>
        <small class="text-body-secondary"><?= htmlspecialchars((string)$item['created_at'], ENT_QUOTES, 'UTF-8') ?></small>
        <?php if ($safeAction): ?><a class="d-block mt-1" href="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" data-notification-action>Ver contenido</a><?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if (empty($item['is_read'])): ?><button class="btn btn-sm btn-outline-primary" type="button" data-notification-read>Leída</button><?php endif; ?>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-notification-delete aria-label="Eliminar">&times;</button>
    </div>
</article>
<?php endforeach; ?>
