<?php $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>
<?php if ($variant === 'editor_gallery'): ?><figure data-media-gallery="1"><?php endif; ?>
<?php foreach ($mediaItems as $item): ?>
<?php if ($variant === 'editor_gallery'): ?><figure><?php else: ?><p><?php endif; ?>
<img data-media-id="<?= (int)$item['media_id'] ?>" src="<?= $escape($item['media_url']) ?>" alt="<?= $escape($item['alt_text'] ?? '') ?>">
<?php if ($variant === 'editor_gallery'): ?></figure><?php else: ?></p><?php endif; ?>
<?php endforeach; ?>
<?php if ($variant === 'editor_gallery'): ?></figure><?php endif; ?>
