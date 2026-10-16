<?php
$selectionID = (int)$ctx['media_id'];
$selectionName = (string)($ctx['original_name'] ?? $ctx['name'] ?? 'Archivo');
$selectionNameEsc = htmlspecialchars($selectionName, ENT_QUOTES, 'UTF-8');
?>
<li class="list-group-item d-flex align-items-center flex-wrap gap-2" data-mp-selection-id="<?= $selectionID ?>">
    <button type="button" class="btn btn-link text-start flex-grow-1 text-break" data-mp-detail="<?= $selectionID ?>"><?= $selectionNameEsc ?></button>
    <div class="btn-group" role="group" aria-label="<?= htmlspecialchars('Ordenar ' . $selectionName, ENT_QUOTES, 'UTF-8') ?>">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-mp-move="-1" aria-label="Mover antes: <?= $selectionNameEsc ?>">↑</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-mp-move="1" aria-label="Mover después: <?= $selectionNameEsc ?>">↓</button>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-mp-remove="<?= $selectionID ?>" aria-label="Quitar de la selección: <?= $selectionNameEsc ?>">Quitar</button>
</li>
