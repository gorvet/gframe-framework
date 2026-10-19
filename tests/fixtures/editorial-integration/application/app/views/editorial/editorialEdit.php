<?php
$article = $data['article'];
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$richTextEditor = ['id' => 'article-content', 'name' => 'content', 'value' => $article['content'], 'preview_url' => site_url . 'preview', 'autosave' => ['url' => site_url . 'ajax/editorial?operation=autosave', 'base_version' => $article['revision'], 'interval' => 5000]];
?>
<main class="container py-4"><h1>Prueba editorial</h1>
<form id="editorialForm" data-url="<?= $escape(site_url . 'ajax/editorial') ?>" data-saved="Operación completada" data-failed="No se pudo completar la operación">
<input type="hidden" name="content_revision" value="<?= $escape($article['revision']) ?>">
<?php include GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.php', 'rich-text-editor'); ?>
<label for="publish_at" class="form-label mt-3">Fecha y hora de publicación</label><input class="form-control" type="datetime-local" step="1" id="publish_at" name="publish_at">
<div class="d-flex flex-wrap gap-2 my-3">
<?php foreach (['save' => 'Guardar', 'schedule' => 'Programar', 'cancel' => 'Cancelar programación', 'trash' => 'Enviar a papelera', 'restore' => 'Restaurar'] as $operation => $label): ?>
<button class="btn btn-primary" type="submit" name="operation" value="<?= $operation ?>"><?= $label ?></button>
<?php endforeach; ?>
</div><p id="editorialResult" role="status"></p></form>
<h2>Revisiones</h2><ul id="editorialHistory"><?php foreach ($data['history'] as $entry): ?><li data-entry-id="<?= (int)$entry['id'] ?>"><?= $escape($entry['revision']) ?></li><?php endforeach; ?></ul>
</main>
