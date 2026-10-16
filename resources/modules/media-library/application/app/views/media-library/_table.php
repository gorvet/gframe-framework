<?php $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>
<div class="table-responsive" tabindex="0" role="region" aria-label="Lista de archivos, desplazable horizontalmente">
  <table class="table table-hover align-middle">
    <caption class="visually-hidden">Archivos de la biblioteca. El botón de cada nombre abre sus detalles.</caption>
    <thead><tr><th scope="col">Archivo</th><th scope="col">Tipo</th><th scope="col">Tamaño</th><th scope="col">Subido por</th><th scope="col">Fecha</th></tr></thead>
    <tbody>
    <?php foreach ($files as $file): ?>
      <tr>
        <td>
          <button type="button" class="btn btn-link text-start p-0 m-list-card media-list-file"
                  data-media-id="<?= (int)$file['media_id'] ?>" data-media-url="<?= $escape($file['media_url']) ?>"
                  data-media-name="<?= $escape($file['name']) ?>" data-media-type="<?= $escape($file['type']) ?>"
                  data-media-mime="<?= $escape($file['mime_type']) ?>" data-media-alt="<?= $escape($file['alt_text']) ?>"
                  data-media-ext="<?= $escape($file['ext']) ?>" data-media-thumb-small="<?= $escape($file['thumb_small']) ?>"
                  data-media-thumb-medium="<?= $escape($file['thumb_medium']) ?>" data-media-thumb-xsmall="<?= $escape($file['thumb_xsmall']) ?>">
            <?= $escape($file['original_name'] ?: $file['name']) ?>
          </button>
          <div class="small text-body-secondary media-list-file"><?= $escape($file['name']) ?></div>
        </td>
        <td><?= $escape($file['mime_type']) ?></td>
        <td class="text-nowrap"><?= number_format((int)($file['size_bytes'] ?? 0) / 1024, 1, ',', '.') ?> KB</td>
        <td><?= $escape($file['uploaded_by'] ?? '-') ?></td>
        <td class="text-nowrap"><?= $escape($file['created_at'] ?? '-') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
