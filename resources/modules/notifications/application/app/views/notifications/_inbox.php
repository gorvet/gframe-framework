<?php
$base = defined('site_url') ? rtrim((string)site_url, '/') : '';
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<?php if ($items === []): ?><p class="text-body-secondary text-center mb-0" data-notifications-empty>No tienes notificaciones.</p><?php endif; ?>
<?php foreach ($items as $item):
    $id = (int)$item['notification_id'];
    $unread = empty($item['is_read']);
    $importance = in_array(($item['importance'] ?? ''), ['warning', 'danger'], true) ? $item['importance'] : 'info';
    $label = ['info' => 'Informativa', 'warning' => 'Aviso', 'danger' => 'Importante'][$importance];
?>
<article class="notification-inbox-item<?= $unread ? ' is-unread' : '' ?>" data-notification-id="<?= $id ?>">
    <a class="notification-summary" data-notification-open="<?= $id ?>" href="<?= $escape($base . '/notifications/view?id=' . $id) ?>">
        <span class="notification-importance notification-importance-<?= $importance ?>" title="<?= $label ?>"><i class="gicon-<?= $importance === 'info' ? 'info' : 'alert' ?>" aria-hidden="true"></i><span class="visually-hidden"><?= $label ?></span></span>
        <span class="notification-copy"><strong class="d-block"><?= $escape($item['title']) ?></strong><span class="notification-excerpt d-block"><?= $escape($item['message']) ?></span><small class="notification-time" title="<?= $escape($item['created_at']) ?>"><?= $escape(\GFrame\Notifications\NotificationTime::relative((string)$item['created_at'])) ?></small></span>
    </a>
    <div class="notification-row-actions">
        <?php if ($unread): ?><span class="notification-unread-dot" title="No leída"><span class="visually-hidden">No leída</span></span><?php endif; ?>
        <div class="dropdown notification-actions-dropdown">
            <button class="btn btn-link btn-sm notification-more" type="button" data-bs-toggle="dropdown" data-bs-config='{"popperConfig":{"strategy":"fixed","modifiers":[{"name":"applyStyles","enabled":true},{"name":"preventOverflow","options":{"boundary":[],"rootBoundary":"viewport","padding":8,"altAxis":true}}]}}' aria-expanded="false" aria-label="Opciones de la notificación">&hellip;</button>
            <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-notification-id="<?= $id ?>" <?= $unread ? 'data-notification-read' : 'data-notification-unread' ?>><?= $unread ? 'Marcar como leída' : 'Marcar como no leída' ?></button></li></ul>
        </div>
    </div>
</article>
<?php endforeach; ?>
