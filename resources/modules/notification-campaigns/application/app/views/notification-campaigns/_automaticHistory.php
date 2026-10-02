<?php $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>
<div class="card"><div class="card-body">
<?php if ($items === []): ?><p class="text-center text-body-secondary py-4 mb-0">No hay envíos automáticos para mostrar.</p>
<?php else: ?><div class="table-responsive"><table class="table table-hover align-middle">
<thead><tr><th scope="col">Campaña</th><th scope="col">Origen</th><th scope="col">Fecha (UTC)</th><th scope="col">Destinatarios</th><th scope="col">Estado</th></tr></thead><tbody>
<?php foreach ($items as $item): $rule = ($automaticModel ?? new \GFrame\Notifications\Campaigns\AutomaticCampaignModel())->definition($item['rule_key']) ?? ['name' => $item['rule_key'] === 'account_deactivated' ? 'Cuenta desactivada' : $item['rule_key']]; ?>
<tr><td><strong><?= $escape($rule['name']) ?></strong><small class="d-block text-body-secondary"><?= $escape($item['title']) ?></small></td><td><?= $item['source'] === 'manual' ? 'Manual' : 'Automático' ?></td><td><?= $escape($item['queued_at']) ?></td><td><?= (int)$item['recipients'] ?></td><td><?= (int)$item['failed'] > 0 ? 'Con errores' : ((int)$item['sent'] === (int)$item['recipients'] ? 'Enviado' : 'En cola') ?><small class="d-block text-body-secondary">Enviados: <?= (int)$item['sent'] ?> · Fallidos: <?= (int)$item['failed'] ?></small></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if ((int)($meta['total_pages'] ?? 1) > 1) PaginationHelper::render((int)$meta['total_pages'], (int)$meta['page']); ?>
<?php endif; ?></div></div>
