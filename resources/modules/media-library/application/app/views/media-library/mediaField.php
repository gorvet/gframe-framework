<?php
$field = array_merge([
    'name' => 'media_id',
    'value' => '',
    'label' => 'Archivo',
    'multiple' => false,
    'accept' => 'all',
    'max' => 50,
    'behavior' => 'append',
    'save_source' => 'library',
    'fragment' => null,
    'remove_one' => true,
], isset($mediaField) && is_array($mediaField) ? $mediaField : []);
$fieldID = 'media-field-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string)$field['name']);
$rawValue = trim((string)(is_array($field['value']) ? '' : $field['value']));
$jsonValue = $rawValue !== '' ? json_decode($rawValue, true) : null;
$ids = is_array($field['value']) ? $field['value'] : (is_array($jsonValue) ? $jsonValue : preg_split('/\s*,\s*/', $rawValue));
$ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids), static fn(int $id): bool => $id > 0)));
$multiple = !empty($field['multiple']);
if ($field['fragment'] === null) $field['fragment'] = $multiple ? 'gthumb' : 'sthumb';
$value = $multiple ? (json_encode($ids) ?: '[]') : (string)($ids[0] ?? '');
?>
<div class="gframe-media-field" data-ml-media-field data-ml-kind="<?= htmlspecialchars((string)$field['accept'], ENT_QUOTES, 'UTF-8') ?>" data-ml-save-source="<?= htmlspecialchars((string)$field['save_source'], ENT_QUOTES, 'UTF-8') ?>" data-ml-max="<?= max(1, min(50, (int)$field['max'])) ?>" data-ml-behavior="<?= $field['behavior'] === 'replace' ? 'replace' : 'append' ?>" data-ml-fragment="<?= $field['fragment'] === 'gthumb' ? 'gthumb' : 'sthumb' ?>" data-ml-remove-one="<?= !empty($field['remove_one']) ? '1' : '0' ?>" <?= $multiple ? 'data-ml-multiple' : '' ?>>
    <label class="form-label" for="<?= htmlspecialchars($fieldID, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$field['label'], ENT_QUOTES, 'UTF-8') ?></label>
    <input type="hidden" id="<?= htmlspecialchars($fieldID, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars((string)$field['name'], ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" data-ml-value>
    <div class="media-field-preview media-field-grid mt-2" data-ml-preview></div>
    <div class="d-flex flex-wrap gap-1">
        <button class="btn btn-sm btn-outline-primary" type="button" data-ml-media-pick><?= $field['accept'] === 'images' ? 'Elegir imagen' : 'Elegir archivo' ?></button>
        <button class="btn btn-sm btn-outline-danger<?= $ids ? '' : ' d-none' ?>" type="button" data-ml-media-clear aria-label="Quitar selección">Quitar</button>
    </div>
</div>
