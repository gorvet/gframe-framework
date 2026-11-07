   <!-- Modal detalles de medias  -->
<div class="modal fade" id="editMediaModal" tabindex="-1" aria-labelledby="media-details-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content ">
      <div class="modal-header">
        <h5 class="modal-title" id="media-details-title">Detalles del archivo</h5>
        <div class="d-flex align-items-center gap-2 ms-auto me-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="editMediaPrev" aria-label="Anterior" title="Anterior">
            <span aria-hidden="true">←</span>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="editMediaNext" aria-label="Siguiente" title="Siguiente">
            <span aria-hidden="true">→</span>
          </button>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div  class="modal-body ">
      <p class="visually-hidden" id="media-details-status" role="status" tabindex="-1"></p>
      <div class="row g-0 media-details-layout">
        <div class="col-lg-7 col-12 media-details-preview">
        
          <img id="img_details" class="card-img-top d-none" alt="">
          <audio id="media-details-audio" class="w-100 d-none" controls preload="none" aria-label="Vista previa de audio"></audio>
          <video id="media-details-video" class="w-100 d-none" controls preload="none" playsinline aria-label="Vista previa de vídeo"></video>

<div id="file_details" class="card-img-top d-none d-flex  align-items-center justify-content-center bg-light" style="min-height: 300px;">
    <div class="text-center position-relative">
        <!-- Icono grande -->
       <img src="<?= site_url. 'public/img/admin/file.png' ?>"
                
             
             class="card-img-top"
              >
        
        <!-- Extensión centrada DENTRO del icono -->
        <div id="file_details_ext" class="badge bg-secondary text-uppercase position-absolute top-50 start-50 translate-middle" style="font-size: 1.2rem; z-index: 2;"></div>
        
         
    </div>
</div>

        </div>
        <div class="col-lg-5 col-12 media-details-attributes">
          <form id="editMedia" class="needs-validation " novalidate>
    <div class="row">
   
    <input type="hidden" id="media_id" name="media_id" value="1">
 <!-- incluir el id del negocio -->

  <dl class="media-file-info mb-3">
    <div><dt>Subido el</dt><dd id="upload_date">—</dd></div>
    <div><dt>Subido por</dt><dd id="uploaded_by">—</dd></div>
    <div><dt>Subido a</dt><dd id="uploaded_to">—</dd></div>
    <div><dt>Visibilidad</dt><dd id="media-visibility-label">—</dd></div>
    <div><dt>Nombre del archivo</dt><dd id="name">—</dd></div>
    <div><dt>Tipo</dt><dd id="mime_type">—</dd></div>
    <div><dt>Tamaño original</dt><dd id="file_weight">—</dd></div>
    <div><dt>Dimensiones</dt><dd id="file_size">—</dd></div>
    <div data-media-optimized-row class="d-none"><dt>Versión optimizada</dt><dd id="media-optimized-info"></dd></div>
  </dl>
  <div class="mb-3"><button type="button" id="media-optimize" class="btn btn-sm btn-outline-primary d-none">Optimizar</button></div>
 
<hr class="my-2">
  
<div class="mb-2 col-12">
<label class="form-label" for="media-original-name">Nombre visible</label>
<input class="form-control" type="text" name="original_name" id="media-original-name" required maxlength="255" disabled autocomplete="off" aria-describedby="media-name-help">
<div class="form-text" id="media-name-help">No cambia el nombre físico ni la URL del archivo.</div>
</div>
<div class="mb-2 col-12   ">
<label class="form-label" for="alt_text">Texto alternativo</label>
<textarea class="form-control" name="alt_text" id="alt_text" rows="3" maxlength="255" aria-describedby="media-alt-help"></textarea>
<div class="form-text" id="media-alt-help">Describe la imagen. Déjalo vacío si es decorativa.</div>
 </div>

 <div class="mb-2 col-12   ">
<label class="form-label" for="img_url">URL</label>
<input id="img_url" class="form-control" type="text" value="" readonly="">
<button id="copyUrlBtn" type="button" class="btn btn-sm btn-outline-secondary my-2" >Copiar la URL</button>
<a id="media-open-file" class="btn btn-sm btn-outline-secondary my-2" target="_blank" rel="noopener noreferrer">Abrir archivo</a>
<a id="media-open-original" class="btn btn-sm btn-outline-secondary my-2 d-none" target="_blank" rel="noopener noreferrer">Abrir original</a>
 </div>

<hr class="my-2">
       <div class="mb-3 col-12   ">
          <a id="delmedia" class="text-danger" href="#">Borrar permanentemente</a> 
 
       </div>
    </div>
                    
      </form>
        </div>
      </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="media-details-discard" class="btn btn-outline-secondary me-auto d-none">Descartar cambios</button>
        <button type="button" class="btn btn-secondary btn-modal" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" id="editMedia_guardar" class="btn btn-primary btn-modal">Guardar</button>
      </div>
    </div>
  </div>
</div>
<?php $mediaAdminMessages = [
  'pending' => 'Guarda o descarta los cambios antes de continuar.', 'loading' => 'Cargando archivo…',
  'saving' => 'Guardando cambios…', 'failed' => 'No se pudo completar la operación.',
  'pixels' => 'píxeles', 'copied' => 'URL copiada.',
  'optimizing' => 'Optimizando imagen…', 'optimize' => 'Optimizar',
]; ?>
<script type="application/json" id="media-admin-messages"><?= json_encode($mediaAdminMessages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
     
