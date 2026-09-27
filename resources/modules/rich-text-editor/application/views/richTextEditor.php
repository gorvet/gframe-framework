<?php
$editor = (array)($richTextEditor ?? []);
$editorID = trim((string)($editor['id'] ?? 'richTextContent'));
$editorName = trim((string)($editor['name'] ?? 'contenido'));
$editorLabel = trim((string)($editor['label'] ?? 'Contenido'));
$editorValue = (string)($editor['value'] ?? '');
$editorRows = max(8, (int)($editor['rows'] ?? 24));
$editorMinHeight = max(320, (int)($editor['min_height'] ?? 580));
$editorRequired = !empty($editor['required']);
$editorLabelClass = trim('form-label fw-semibold ' . (string)($editor['label_class'] ?? ''));
$editorHelp = trim((string)($editor['help'] ?? ''));
?>

<label for="<?= htmlspecialchars($editorID, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($editorLabelClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($editorLabel, ENT_QUOTES, 'UTF-8') ?></label>
<textarea
    class="form-control js-rich-text-editor"
    id="<?= htmlspecialchars($editorID, ENT_QUOTES, 'UTF-8') ?>"
    name="<?= htmlspecialchars($editorName, ENT_QUOTES, 'UTF-8') ?>"
    rows="<?= $editorRows ?>"
    data-editor-min-height="<?= $editorMinHeight ?>"
    <?= $editorRequired ? 'required' : '' ?>
><?= htmlspecialchars($editorValue, ENT_QUOTES, 'UTF-8') ?></textarea>
<?php if ($editorHelp !== ''): ?>
    <div class="form-text"><?= htmlspecialchars($editorHelp, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

