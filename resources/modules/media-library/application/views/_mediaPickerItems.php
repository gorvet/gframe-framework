<?php foreach ((array)($mediaItems ?? []) as $item): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <button class="card media-picker-item h-100 w-100 text-start" type="button" data-media-picker-id="<?= (int)($item['media_id'] ?? 0) ?>" data-media-picker-kind="<?= htmlspecialchars((string)($item['kind'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <span class="ratio ratio-4x3 bg-body-tertiary">
                <?php if (($item['kind'] ?? '') === 'images'): ?>
                    <img class="w-100 h-100 object-fit-cover" src="<?= htmlspecialchars(!empty($item['remote_url']) ? (string)$item['remote_url'] : UrlHelper::assetUrl('public/' . ltrim((string)($item['path'] ?? ''), '/')), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)($item['alt_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                    <span class="d-flex h-100 align-items-center justify-content-center fw-semibold"><?= htmlspecialchars(mb_strtoupper((string)pathinfo((string)($item['name'] ?? ''), PATHINFO_EXTENSION), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </span>
            <span class="card-body d-block text-truncate"><?= htmlspecialchars((string)($item['original_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
    </div>
<?php endforeach; ?>
