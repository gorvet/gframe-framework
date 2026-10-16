<?php
$editor = (array)($richTextEditor ?? []);
$editorID = trim((string)($editor['id'] ?? 'richTextContent'));
$editorID = preg_replace('/[^A-Za-z0-9_-]+/', '-', $editorID) ?: 'richTextContent';
$editorID = trim($editorID, '-_');
if ($editorID === '') {
    $editorID = 'richTextContent';
}
if (preg_match('/^[0-9]/', $editorID) === 1) {
    $editorID = 'editor-' . $editorID;
}
$editorName = trim((string)($editor['name'] ?? 'contenido'));
$editorLabel = trim((string)($editor['label'] ?? 'Contenido'));
$editorValue = (string)($editor['value'] ?? '');
$editorRows = max(8, (int)($editor['rows'] ?? 24));
$editorMinHeight = max(320, (int)($editor['min_height'] ?? 580));
$editorRequired = !empty($editor['required']);
$editorLabelClass = trim('form-label fw-semibold ' . (string)($editor['label_class'] ?? ''));
$editorHelp = trim((string)($editor['help'] ?? ''));
$editorMedia = !empty($editor['media_library']);
$editorSaveButton = trim((string)($editor['save_button'] ?? ''));
$editorProtect = ($editor['protect_changes'] ?? true) !== false;
$editorDraftKey = trim((string)($editor['draft_key'] ?? ''));
$editorAutosave = (array)($editor['autosave'] ?? []);
if ($editorAutosave !== []) {
    if (!is_string($editorAutosave['url'] ?? null) || trim($editorAutosave['url']) === '' || !is_string($editorAutosave['base_version'] ?? null) || $editorAutosave['base_version'] === '' || strlen($editorAutosave['base_version']) > 128) {
        throw new \InvalidArgumentException('El autoguardado requiere URL y base_version del contenido autorizado.');
    }
    $editorAutosave = ['url' => $editorAutosave['url'], 'base_version' => $editorAutosave['base_version'],
        'interval' => max(5000, min(120000, (int)($editorAutosave['interval'] ?? 15000)))];
}
if (strlen($editorDraftKey) > 512) {
    throw new \InvalidArgumentException('draft_key no puede superar 512 bytes.');
}
?>

<label for="<?= htmlspecialchars($editorID, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($editorLabelClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($editorLabel, ENT_QUOTES, 'UTF-8') ?></label>
<textarea
    class="form-control js-rich-text-editor"
    id="<?= htmlspecialchars($editorID, ENT_QUOTES, 'UTF-8') ?>"
    name="<?= htmlspecialchars($editorName, ENT_QUOTES, 'UTF-8') ?>"
    rows="<?= $editorRows ?>"
    data-editor-min-height="<?= $editorMinHeight ?>"
    data-editor-more="Más herramientas"
    data-editor-outline="Esquema del artículo"
    data-editor-outline-empty="No hay títulos en el texto"
    data-editor-protect="<?= $editorProtect ? '1' : '0' ?>"
    <?php if ($editorAutosave !== []): ?>
    data-editor-autosave="<?= htmlspecialchars(json_encode($editorAutosave, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>"
    data-editor-autosave-messages="<?= htmlspecialchars(json_encode([
        'loading' => 'Comprobando el autoguardado privado…',
        'saving' => 'Autoguardando una copia privada…',
        'saved' => 'Autoguardado privado actualizado. Los cambios aún no están publicados.',
        'failed' => 'No se pudo autoguardar en el servidor. Conserva el texto; se reintentará sin publicarlo.',
        'conflict' => 'El contenido o su autoguardado cambió. Recarga la página antes de continuar; tu texto sigue en el editor.',
        'available' => 'Hay un autoguardado privado recuperable. Recuperarlo sustituye el texto actual y se puede deshacer.',
        'different' => 'El contenido publicado cambió desde este autoguardado. Recuperarlo sustituye el texto actual y se puede deshacer.',
        'discard' => 'Descartar autoguardado', 'cancel' => 'Cancelar',
        'discard_question' => '¿Descartar esta copia privada del servidor? El texto del editor no se modificará.',
        'confirm_missing' => 'Carga los recursos de recuperación para confirmar el descarte.',
        'published' => 'Contenido guardado. El autoguardado privado está preparado para los próximos cambios.',
        'revision_missing' => 'El contenido se guardó. Recarga la página para continuar con el autoguardado de la nueva versión.',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>"
    <?php endif; ?>
    <?php if ($editorDraftKey !== ''): ?>
    data-editor-draft-key="<?= htmlspecialchars($editorDraftKey, ENT_QUOTES, 'UTF-8') ?>"
    data-editor-draft-messages="<?= htmlspecialchars(json_encode([
        'available' => 'Hay un borrador local recuperable. Recuperarlo sustituye el texto actual y se puede deshacer.',
        'conflict' => 'El contenido guardado ha cambiado desde este borrador. Recuperarlo sustituye el texto actual y se puede deshacer.',
        'stored' => 'Copia local actualizada en este navegador. Pendiente de guardar en el servidor.',
        'failed' => 'No se pudo actualizar la copia local. Conserva el texto antes de salir.',
        'restored' => 'Borrador recuperado. Pendiente de guardar en el servidor.',
        'discard' => 'Descartar copia local',
        'discard_question' => '¿Descartar esta copia local? El texto del editor no se modificará.',
        'cancel' => 'Cancelar',
        'confirm_missing' => 'La confirmación no está disponible. Carga los recursos de recuperación para descartar copias.',
    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
    <?php endif; ?>
    data-editor-writing="<?= htmlspecialchars(json_encode(['paragraph' => 'Párrafo', 'heading2' => 'Título 2', 'heading3' => 'Título 3', 'bullet' => 'Lista con viñetas', 'number' => 'Lista numerada', 'quote' => 'Cita', 'rule' => 'Separador'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
    <?php if ($editorSaveButton !== ''): ?>
    data-editor-save-button="<?= htmlspecialchars($editorSaveButton, ENT_QUOTES, 'UTF-8') ?>"
    data-editor-save-label="Guardar"
    <?php endif; ?>
    <?php if ($editorMedia): ?>
    data-editor-media="1"
    data-editor-media-image="Imagen de biblioteca"
    data-editor-media-gallery="Galería de biblioteca"
    data-editor-media-pending="Termina la selección de imágenes antes de continuar."
    data-editor-media-failed="No se pudieron insertar las imágenes."
    data-editor-media-missing="La biblioteca de medios no está disponible."
    data-editor-media-custom="La galería contiene texto o imágenes sin referencia. Revísala antes de cambiar la selección."
    data-editor-media-replace="Cambiar imagen"
    data-editor-media-edit-gallery="Editar galería"
    data-editor-media-remove-image="Quitar imagen del texto"
    data-editor-media-remove-gallery="Quitar galería del texto"
    <?php endif; ?>
    <?= $editorRequired ? 'required' : '' ?>
><?= htmlspecialchars($editorValue, ENT_QUOTES, 'UTF-8') ?></textarea>
<?php if ($editorAutosave !== []): ?>
    <div id="<?= htmlspecialchars($editorID . '-autosave', ENT_QUOTES, 'UTF-8') ?>" class="mt-2">
        <p class="form-text" role="status" aria-live="polite" data-autosave-status></p>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-autosave-retry hidden>Comprobar autoguardado</button>
        <div class="alert alert-warning" data-autosave-recovery hidden>
            <p class="mb-2" data-autosave-message></p>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-primary" data-autosave-restore>Recuperar autoguardado</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-autosave-discard>Descartar autoguardado</button>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if ($editorDraftKey !== ''): ?>
    <div id="<?= htmlspecialchars($editorID . '-recovery', ENT_QUOTES, 'UTF-8') ?>" class="alert alert-warning mt-2" hidden>
        <p class="mb-2" data-editor-draft-message></p>
        <div data-editor-draft-actions>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-primary" data-editor-draft-restore>Recuperar borrador</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-draft-discard>Descartar copia local</button>
          </div>
        </div>
    </div>
    <p id="<?= htmlspecialchars($editorID . '-draft-status', ENT_QUOTES, 'UTF-8') ?>" class="form-text" role="status" aria-live="polite" hidden></p>
<?php endif; ?>
<?php if ($editorHelp !== ''): ?>
    <div class="form-text"><?= htmlspecialchars($editorHelp, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
