<?php $recent = (array)$data; $maxUploadMB = (float)($data['meta']['max_upload_bytes'] ?? (new \GFrame\Media\MediaProcessor())->getMaxUploadBytes()) / 1048576; ?>

<div class="pagetitle">
    <div class="d-flex align-items-center flex-wrap">
        <div class="d-inline-block me-2">
            <h1>Medios</h1>
        </div>
        <div class="d-inline-block">
            <button type="button" class="btn btn-primary ms-0 ms-md-3 mt-3 mt-md-0" id="knowledgeMediaAdd">Añadir archivo</button>
        </div>
    </div>
</div>

<div id="knowledgeMediaUpload" class="card p-4 mb-4 d-none">
    <div class="drop-zone text-center p-4" data-ml-upload data-ml-dropzone>
        <h2 class="h5 mb-3">Arrastra aquí los archivos para subirlos o selecciónalos desde tu equipo.</h2>
        <input type="file" id="knowledgeMediaFile" data-ml-file multiple hidden>
        <button type="button" class="btn btn-secondary ms-2" id="knowledgeMediaClose">Cancelar</button>
        <button type="button" class="btn btn-primary" id="knowledgeMediaSelect" data-ml-uploadbtn>Seleccionar archivos</button>
        <div class="small text-muted mt-3">Tamaño máximo por archivo: <span data-ml-max-mb-value><?= $maxUploadMB ?></span> MB.</div>
    </div>
</div>

<div id="knowledgeMediaList"
     class="row mediasList"
     data-ml-mount
     data-ml-noauto
     data-ml-save-source="library"
     data-ml-max-mb="<?= $maxUploadMB ?>">
    <?= (string)($data['html'] ?? '') ?>
</div>

<?php include \GFrame\Modules\ModuleRuntime::file('views', 'media-library/mediaEditModal.php', 'media-library'); ?>
<?php include \GFrame\Modules\ModuleRuntime::file('views', 'media-library/mediaPickerModal.php', 'media-library'); ?>
