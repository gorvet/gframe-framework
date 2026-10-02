<?php
$labels = ['draft' => 'Borrador', 'scheduled' => 'Programada', 'running' => 'En proceso', 'paused' => 'Pausada', 'completed' => 'Completada', 'failed' => 'Con errores', 'cancelled' => 'Cancelada'];
$canManage = $canManage ?? false;
$base = rtrim((string)site_url, '/');
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="card"><div class="card-body">
<?php if ($campaigns === []): ?>
    <p class="text-center text-body-secondary py-4 mb-0">No hay campañas para mostrar.</p>
<?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th scope="col">Campaña</th><th scope="col">Canales</th><th scope="col">Estado</th><th scope="col">Programación</th><?php if ($canManage): ?><th scope="col" class="text-end">Acciones</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($campaigns as $campaign): $status = (string)($campaign['status'] ?? 'draft'); $id = (int)$campaign['campaign_id']; $channels = json_decode((string)($campaign['channels_json'] ?? '[]'), true) ?: []; $recurring = ($campaign['recurrence'] ?? 'once') !== 'once'; ?>
            <tr><td><strong><?= $escape($campaign['title']) ?></strong><?php if ($recurring): ?><small class="d-block text-body-secondary"><?= $campaign['recurrence'] === 'daily' ? 'Diaria' : 'Semanal' ?></small><?php endif; ?></td><td><?= $escape(implode(', ', array_map(static fn($channel) => $channel === 'inbox' ? 'Notificaciones' : ($channel === 'email' ? 'Correo' : $channel), $channels))) ?></td><td><?= $escape($labels[$status] ?? $status) ?></td><td><?= $escape($campaign['scheduled_at'] ?? 'Inmediata') ?></td>
            <?php if ($canManage): ?><td class="text-end">
                <?php if ($canManage): ?>
                <div class="dropdown"><button class="btn btn-outline-secondary btn-list-actions btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-config='{"popperConfig":{"strategy":"fixed"}}' aria-expanded="false">Administrar</button><ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= $escape($base . '/admin/notifications/campaigns/new?source=' . $id) ?>">Reciclar campaña</a></li>
                    <?php if (in_array($status, ['draft', 'scheduled', 'paused'], true) || ($recurring && $status !== 'cancelled')): ?><li><a class="dropdown-item" href="<?= $escape($base . '/admin/notifications/campaigns/edit?id=' . $id) ?>">Editar contenido</a></li><?php endif; ?>
                    <?php if ($status === 'paused'): ?>
                    <li><button class="dropdown-item" type="button" data-campaign-action="resume" data-campaign-id="<?= $id ?>">Reanudar</button></li>
                    <?php elseif ($status !== 'cancelled' && ($status !== 'completed' || $recurring)): ?>
                    <li><button class="dropdown-item" type="button" data-campaign-action="pause" data-campaign-id="<?= $id ?>">Pausar</button></li>
                    <?php if ($status !== 'completed'): ?><li><button class="dropdown-item" type="button" data-campaign-action="run" data-campaign-id="<?= $id ?>">Procesar lote</button></li><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($status !== 'cancelled' && ($status !== 'completed' || $recurring)): ?><li><button class="dropdown-item text-danger" type="button" data-campaign-action="cancel" data-campaign-id="<?= $id ?>">Cancelar campaña</button></li><?php endif; ?>
                </ul></div>
                <?php endif; ?>
            </td><?php endif; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if ((int)($meta['total_pages'] ?? 1) > 1) PaginationHelper::render((int)$meta['total_pages'], (int)($meta['page'] ?? 1)); ?>
<?php endif; ?>
</div></div>
