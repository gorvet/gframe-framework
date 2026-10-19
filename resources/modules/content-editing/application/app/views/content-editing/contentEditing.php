<?php

$editing = $contentEditing ?? [];
if (!empty($editing['readonly'])) return;
$escapeEditing = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div data-content-editing data-presence-url="<?= $escapeEditing((string)($editing['url'] ?? '')) ?>" data-presence-error="No se pudo comprobar si hay otras sesiones de edición.">
    <input type="hidden" name="content_revision" value="<?= $escapeEditing((string)($editing['revision'] ?? '')) ?>">
    <p class="small text-body-secondary mb-2" role="status" aria-live="polite" data-content-presence-status hidden></p>
</div>
