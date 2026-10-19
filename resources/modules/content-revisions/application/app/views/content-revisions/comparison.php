<?php
// The project authorizes the record and supplies compareRevisions()['data'].
$revisionComparison = $revisionComparison ?? [];
$escapeRevision = static fn(mixed $value): string => htmlspecialchars(is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="table-responsive">
<table class="table"><thead><tr><th scope="col">Campo</th><th scope="col">Versión anterior</th><th scope="col">Versión posterior</th></tr></thead><tbody>
<?php foreach ($revisionComparison as $field => $change): ?>
<tr><th scope="row"><?= $escapeRevision($field) ?></th><td><pre class="text-wrap mb-0"><?= $escapeRevision($change['before']) ?></pre></td><td><pre class="text-wrap mb-0"><?= $escapeRevision($change['after']) ?></pre></td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
