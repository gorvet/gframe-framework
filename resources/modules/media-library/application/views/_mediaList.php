<div class="row g-3" data-ml-list>
    <?php foreach ((array)($items ?? []) as $item): ?>
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
            <article class="card media-item h-100" data-media-id="<?= (int)$item['media_id'] ?>">
                <div class="card-body">
                    <div class="media-preview mb-3">
                        <?php if (($item['kind'] ?? '') === 'images'): ?>
                            <img src="<?= htmlspecialchars(!empty($item['remote_url']) ? (string)$item['remote_url'] : UrlHelper::assetUrl('public/' . ltrim((string)$item['path'], '/')), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)($item['alt_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <span><?= htmlspecialchars(mb_strtoupper((string)pathinfo((string)$item['name'], PATHINFO_EXTENSION), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <strong class="d-block text-truncate" title="<?= htmlspecialchars((string)$item['original_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['original_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <small class="text-body-secondary"><?= !empty($item['remote_url']) ? 'Enlace externo' : number_format(((int)$item['size_bytes']) / 1024, 1) . ' KB' ?></small>
                </div>
                <div class="card-footer bg-transparent pt-0 d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-outline-primary media-edit" type="button">Editar</button>
                    <button class="btn btn-sm btn-outline-danger media-delete" type="button">Eliminar</button>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>
<?php if (($items ?? []) === []): ?><div class="alert alert-light border text-center">No hay archivos para mostrar.</div><?php endif; ?>
<?php PaginationHelper::render((int)($meta['total_pages'] ?? 1), (int)($meta['page'] ?? 1)); ?>
