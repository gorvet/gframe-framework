<p class="mb-3">Destinatarios elegibles: <strong><?= (int)$total ?></strong></p>
<?php if ($sample !== []): ?><ul class="list-group list-group-flush"><?php foreach ($sample as $user): ?><li class="list-group-item px-0"><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($total > count($sample)): ?><p class="small text-body-secondary mt-3 mb-0">Se muestran los primeros <?= count($sample) ?> usuarios.</p><?php endif; ?>
