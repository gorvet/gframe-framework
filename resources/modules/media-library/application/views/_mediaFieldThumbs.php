<?php foreach ((array)($mediaItems ?? []) as $item): ?>
    <?php $isImage = ($item['kind'] ?? '') === 'images'; ?>
    <div class="media-thumb <?= $variant === 'gthumb' ? 'media-thumb--large' : '' ?> d-inline-flex align-items-center gap-2 border rounded p-2" data-ml-id="<?= (int)$item['media_id'] ?>">
        <?php if ($isImage): ?>
            <img class="media-thumb-image" src="<?= htmlspecialchars(!empty($item['remote_url']) ? (string)$item['remote_url'] : UrlHelper::assetUrl('public/' . ltrim((string)$item['path'], '/')), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)($item['alt_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
            <span class="media-thumb-extension" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper((string)pathinfo((string)$item['name'], PATHINFO_EXTENSION), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <span class="small text-truncate" title="<?= htmlspecialchars((string)$item['original_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['original_name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($allowRemoveOne ?? true): ?><button class="btn btn-sm btn-outline-secondary" type="button" data-ml-media-remove aria-label="Quitar <?= htmlspecialchars((string)$item['original_name'], ENT_QUOTES, 'UTF-8') ?>">&times;</button><?php endif; ?>
    </div>
<?php endforeach; ?>
