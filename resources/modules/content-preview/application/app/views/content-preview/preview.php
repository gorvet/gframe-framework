<?php
$preview = $contentPreview ?? [];
$escapePreview = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div data-content-preview data-state-url="<?= $escapePreview((string)($preview['state_url'] ?? '')) ?>" data-version="<?= $escapePreview((string)($preview['version'] ?? '')) ?>" data-error="No se pudo actualizar la vista previa. Recarga para comprobar tu acceso.">
<p class="small text-body-secondary" role="status" aria-live="polite" data-preview-status hidden></p>
</div>
