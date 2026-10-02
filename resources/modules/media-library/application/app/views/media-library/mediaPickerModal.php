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
<div class="modal fade" id="mediaPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Biblioteca de medios</h5>
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
            <div class="row mediasList" data-ml-mount <?php if ($tenantID > 0): ?>data-ml-tenant-id="<?= $tenantID; ?>"<?php endif; ?>></div>
          </div>

          <div class="tab-pane fade" id="mp-upload" role="tabpanel">
            <div class="drop-zone p-4 text-center" data-ml-upload data-ml-dropzone>
              <h2 class="px-4 pt-4 px-0">Arrastra aqui los archivos para subirlos<br>o</h2>
              <button class="btn btn-primary" type="button" data-ml-uploadbtn>Seleccionar</button>
              <input type="file" data-ml-file multiple hidden>
              <div class="pb-4 pt-4 text-center"><small class="text-muted">Tamano maximo de archivo: <span data-ml-max-mb-value>10</span> MB</small></div>
            </div>
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
              <button type="button" class="btn btn-primary" data-ml-url-add>Anadir</button>
            </div>
            <div class="form-text mb-2">
              Se registra solo el enlace externo (hotlink), no se guarda archivo fisico en el servidor.
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
      </div>

      <div class="modal-footer">
        <div class="me-auto small text-muted" id="mp-selected-label">Ninguna seleccionada</div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="mp-insert" disabled>Usar</button>
      </div>

    </div>
  </div>
</div>
