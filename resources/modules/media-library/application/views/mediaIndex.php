<?php
$library = (array)($data['data']['library'] ?? []);
$items = (array)($library['data'] ?? []);
$meta = (array)($library['meta'] ?? []);
?>
<div class="container py-4 py-lg-5" data-ml-mount data-ml-save-source="library">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div><h1 class="h2 mb-1">Biblioteca multimedia</h1><p class="text-body-secondary mb-0">Administra los archivos disponibles para el contenido.</p><small class="text-body-secondary" id="media-quota"></small></div>
        <div class="d-flex gap-2"><button class="btn btn-outline-secondary" id="media-sync" type="button">Sincronizar</button><label class="btn btn-primary mb-0">Añadir archivo<input class="d-none" id="media-upload" type="file" multiple></label></div>
    </div>
    <form class="row g-2 mb-4" method="get" data-ml-filters>
        <div class="col-12 col-md"><input class="form-control" name="search" value="<?= htmlspecialchars((string)($_GET['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar archivos"></div>
        <div class="col-12 col-md-3"><select class="form-select" name="kind"><option value="all">Todos los tipos</option><?php foreach (['images' => 'Imágenes', 'videos' => 'Videos', 'audios' => 'Audio', 'docs' => 'Documentos'] as $value => $label): ?><option value="<?= $value ?>" <?= ($_GET['kind'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-3"><label class="visually-hidden" for="media-filter-month">Mes</label><input class="form-control" id="media-filter-month" name="ym" type="month" value="<?= htmlspecialchars((string)($_GET['ym'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-primary w-100" type="submit">Filtrar</button></div>
    </form>
    <form class="row g-2 mb-4" data-ml-hotlink>
        <div class="col-12 col-lg"><label class="visually-hidden" for="media-hotlink-url">URL HTTPS del archivo</label><input class="form-control" id="media-hotlink-url" name="media_url" type="url" placeholder="https://ejemplo.com/imagen.jpg" required></div>
        <div class="col-12 col-lg-3"><label class="visually-hidden" for="media-hotlink-name">Nombre opcional</label><input class="form-control" id="media-hotlink-name" name="name" maxlength="255" placeholder="Nombre opcional"></div>
        <div class="col-12 col-lg-auto"><button class="btn btn-outline-primary w-100" type="submit">Añadir desde URL</button></div>
    </form>
    <div data-ml-results>
        <?php include __DIR__ . '/_mediaList.php'; ?>
    </div>
</div>
<div class="modal fade" id="media-details-modal" tabindex="-1" aria-labelledby="media-details-title" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content needs-validation" id="media-details-form" novalidate>
        <div class="modal-header"><h2 class="modal-title fs-5" id="media-details-title">Detalles del archivo</h2><div class="d-flex gap-2 ms-auto me-3"><button class="btn btn-sm btn-outline-secondary" type="button" data-ml-details-prev aria-label="Archivo anterior">Anterior</button><button class="btn btn-sm btn-outline-secondary" type="button" data-ml-details-next aria-label="Archivo siguiente">Siguiente</button></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body"><input type="hidden" name="media_id"><div class="media-preview mb-3" data-ml-details-preview><img class="d-none" alt=""><span class="d-none" data-ml-details-extension></span></div><p class="small text-body-secondary"><span data-ml-details-date></span> · <span data-ml-details-type></span> · <span data-ml-details-size></span></p><div class="mb-3"><label class="form-label" for="media-original-name">Nombre</label><input class="form-control" id="media-original-name" name="original_name" maxlength="255" required></div><div class="mb-3"><label class="form-label" for="media-alt-text">Texto alternativo</label><input class="form-control" id="media-alt-text" name="alt_text" maxlength="255"><div class="form-text">Describe el contenido visual para accesibilidad.</div></div><label class="form-label" for="media-details-url">URL</label><div class="input-group"><input class="form-control" id="media-details-url" type="url" readonly><button class="btn btn-outline-secondary" type="button" data-ml-copy-url>Copiar</button></div></div>
        <div class="modal-footer"><button class="btn btn-outline-danger me-auto" type="button" data-ml-details-delete>Eliminar</button><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form></div>
</div>
