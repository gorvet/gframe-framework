<?php
  // Reusable Media Picker Modal (v2) - uses MediaLibrary.js
  $tenantID = 0;
  $tenantKey = defined('TENANT') ? (string)TENANT : '';
  if ($tenantKey !== '' && isset($routeParams['params'][$tenantKey])) {
    $tenantID = (int)$routeParams['params'][$tenantKey];
  } elseif (isset($tenant_id)) {
    $tenantID = (int)$tenant_id;
  }
?>
<div class="modal fade" id="mediaPickerModal" tabindex="-1" aria-hidden="true" aria-labelledby="mp-title">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content">
      <script type="application/json" data-mp-messages><?= json_encode([
          'file' => 'Archivo',
          'wait_save' => 'Espera a que termine el guardado.',
          'dirty' => 'Guarda o descarta los cambios antes de continuar.',
          'loading_details' => 'Cargando atributos…',
          'details_failed' => 'No se pudieron cargar los atributos.',
          'selection_failed' => 'No se pudo consultar la selección.',
          'selection_unavailable' => 'Algún archivo de la selección ya no está disponible. Revisa la selección.',
          'selection_retry' => 'No se pudo consultar la selección. Vuelve a intentarlo.',
          'unsaved' => 'Cambios sin guardar.',
          'saving' => 'Guardando…',
          'save_failed' => 'No se pudieron guardar los atributos.',
          'save_denied' => 'No tienes permiso para guardar o los datos no son válidos.',
          'saved' => 'Atributos guardados.',
          'limit' => 'Hay archivos guardados que no se seleccionaron porque se alcanzó el máximo.',
          'incompatible' => 'Algún archivo guardado no está disponible para este selector.',
          'copied' => 'URL copiada.',
          'copy_failed' => 'No se pudo copiar la URL.',
          'optimizing' => 'Optimizando imagen…',
          'optimize' => 'Optimizar',
          'optimize_failed' => 'No se pudo optimizar la imagen.',
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

      <div class="modal-header">
        <h5 class="modal-title" id="mp-title">Biblioteca de medios</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mp-library" type="button" role="tab">
              Biblioteca
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mp-upload" type="button" role="tab">
              Subir
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mp-hotlink" type="button" role="tab">
              Desde URL
            </button>
          </li>
        </ul>

        <div class="tab-content pt-3">
          <div class="tab-pane fade show active" id="mp-library" role="tabpanel">
            <!-- MediaLibrary injects _mlist.php HTML here -->
            <div class="row g-0 media-picker-layout">
              <div class="col-12 col-lg-8 media-picker-library">
                <div class="row mediasList" data-ml-mount <?php if ($tenantID > 0): ?>data-ml-tenant-id="<?= $tenantID; ?>"<?php endif; ?>></div>
              </div>
              <aside class="col-12 col-lg-4 media-picker-details" aria-labelledby="mp-details-title">
                <h6 id="mp-details-title">Detalles del archivo</h6>
                <p data-mp-details-empty class="text-muted">Selecciona un archivo para consultar sus atributos.</p>
                <p data-mp-details-loading class="small text-body-secondary d-none">Cargando atributos…</p>
                <div data-mp-details-form class="d-none">
                  <div class="media-picker-preview mb-3">
                    <img data-mp-preview class="media-picker-details-preview d-none" alt="">
                    <span data-mp-preview-file class="text-body-secondary d-none">Vista previa no disponible</span>
                  </div>
                  <dl class="media-file-info mb-3">
                    <div><dt>Nombre del archivo</dt><dd data-mp-file-name></dd></div>
                    <div><dt>Tipo</dt><dd data-mp-file-type></dd></div>
                    <div><dt>Tamaño original</dt><dd data-mp-file-size></dd></div>
                    <div data-mp-dimensions-row><dt>Dimensiones</dt><dd data-mp-file-dimensions></dd></div>
                    <div data-mp-optimized-row class="d-none"><dt>Versión optimizada</dt><dd data-mp-file-optimized></dd></div>
                  </dl>
                  <div class="mb-3"><button type="button" data-mp-optimize class="btn btn-outline-primary btn-sm d-none" disabled>Optimizar</button></div>
                  <label for="mp-original-name" class="form-label">Nombre visible</label>
                  <input id="mp-original-name" data-mp-name class="form-control mb-2" maxlength="255" required disabled>
                  <label for="mp-alt-text" class="form-label">Texto alternativo</label>
                  <textarea id="mp-alt-text" data-mp-alt class="form-control mb-2" rows="3" maxlength="255" aria-describedby="mp-alt-help" disabled></textarea>
                  <p id="mp-alt-help" class="form-text">Describe la imagen para quien no pueda verla. Puede quedar vacío si es decorativa.</p>
                  <label for="mp-file-url" class="form-label">URL del archivo</label>
                  <input id="mp-file-url" class="form-control mb-2" readonly disabled>
                  <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-mp-copy disabled>Copiar URL</button>
                    <a data-mp-original class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">Abrir original</a>
                  </div>
                  <div class="d-flex flex-wrap justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-mp-discard disabled>Descartar cambios</button>
                    <button type="button" class="btn btn-primary btn-sm" data-mp-save disabled>Guardar atributos</button>
                  </div>
                </div>
                <p class="visually-hidden" data-mp-details-status role="status"></p>
              </aside>
            </div>
          </div>

          <div class="tab-pane fade" id="mp-upload" role="tabpanel">
            <div class="drop-zone p-4 text-center" data-ml-upload data-ml-dropzone>
              <h2 class="px-4 pt-4 px-0">Arrastra aquí los archivos para subirlos<br>o</h2>
              <button class="btn btn-primary" type="button" data-ml-uploadbtn>Seleccionar</button>
              <input type="file" data-ml-file multiple hidden>
              <div class="pb-4 pt-4 text-center"><small class="text-muted">Tamaño máximo de archivo: <span data-ml-max-mb-value>10</span> MB</small></div>
            </div>
            <?php include \GFrame\Modules\ModuleRuntime::file('views', 'media-library/_uploadProgress.php', 'media-library'); ?>
          </div>

          <div class="tab-pane fade" id="mp-hotlink" role="tabpanel">
            <label for="mp-hotlink-url" class="form-label">URL del archivo</label>
            <div class="input-group mb-2">
              <input type="url"
                     id="mp-hotlink-url"
                     class="form-control"
                     placeholder="https://dominio.com/archivo.jpg"
                     data-ml-url-input
                     autocomplete="off">
              <button type="button" class="btn btn-primary" data-ml-url-add>Añadir</button>
            </div>
            <div class="form-text mb-2">
              Se registra solo el enlace externo (hotlink), no se guarda archivo físico en el servidor.
            </div>
            <label for="mp-hotlink-name" class="form-label">Nombre (opcional)</label>
            <input type="text"
                   id="mp-hotlink-name"
                   class="form-control"
                   placeholder="Nombre visible en la biblioteca"
                   data-ml-url-name
                   maxlength="255"
                   autocomplete="off">
          </div>
        </div>
        <p class="small mt-3 mb-0 d-none flex-shrink-0" data-mp-notice role="status"></p>
        <div class="mt-3 d-none media-picker-selection" data-mp-selection aria-labelledby="mp-selection-title">
          <h6 id="mp-selection-title">Archivos seleccionados</h6>
          <ol class="list-group" data-mp-selection-list></ol>
          <p class="small text-muted mt-2">Quitar de esta selección no borra archivos de la biblioteca.</p>
        </div>
      </div>

      <div class="modal-footer">
        <div class="me-auto small text-muted" id="mp-selected-label" role="status">Ninguna seleccionada</div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="mp-insert" disabled>Usar</button>
      </div>

    </div>
  </div>
</div>
