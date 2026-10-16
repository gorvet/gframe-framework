   <!-- Modal detalles de medias  -->
<div class="modal fade" id="editMediaModal" tabindex="-1" aria-labelledby="media-details-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
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
      <p class="small text-body-secondary" id="media-details-status" role="status" tabindex="-1"></p>
      <div class="row">
        <div class="col-md-5 col-12">
        
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
        <div class="col-md-7 col-12">
          <form id="editMedia" class="needs-validation " novalidate>
    <div class="row">
   
    <input type="hidden" id="media_id" name="media_id" value="1">
 <!-- incluir el id del negocio -->

  <p class="filedatas">
  <label class="form-label" for="alt_text">Subido el: </label>
  <span id="upload_date">fecha</span>
  </p>
 <p class="filedatas">
  <label class="form-label" for="alt_text">Subido por: </label>
  <span id="uploaded_by">-</span>
  </p>
 <p class="filedatas">
  <label class="form-label" for="alt_text">Subido a: </label>
  <span id="uploaded_to">-</span>
   </p>
 <p class="filedatas">
  <label class="form-label" for="alt_text">Tipo: </label>
  <span id="mime_type">-</span>
  </p>
 <p class="filedatas">
  <label class="form-label" for="alt_text">Nombre del archivo: </label>
  <span id="name">-</span>
   </p>
 <p class="filedatas">
  <label class="form-label" for="alt_text">Tamaño del archivo: </label>
  <span id="file_weight">-</span>
  </p>
 <p class="filedatas">
  <label class="form-label" for="alt_text">Dimensiones: </label>
  <span id="file_size">-</span>
  </p>
 
<hr class="my-2">
  
<div class="mb-2 col-12">
<label class="form-label" for="media-original-name">Nombre visible</label>
<input class="form-control" type="text" name="original_name" id="media-original-name" required maxlength="255" disabled autocomplete="off" aria-describedby="media-name-help">
<div class="form-text" id="media-name-help">No cambia el nombre físico ni la URL del archivo.</div>
</div>
<div class="mb-2 col-12   ">
<label class="form-label" for="alt_text">Texto alternativo</label>
<input class="form-control" type="text" name="alt_text"   id="alt_text" value="" autocomplete="off ">
 </div>

 <div class="mb-2 col-12   ">
<label class="form-label" for="img_url">URL</label>
<input id="img_url" class="form-control" type="text" value="" readonly="">
<button id="copyUrlBtn" type="button" class="btn btn-sm btn-outline-secondary my-2" >Copiar la URL</button>
<a id="media-open-file" class="btn btn-sm btn-outline-secondary my-2" target="_blank" rel="noopener noreferrer">Abrir archivo</a>
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
]; ?>
<script type="application/json" id="media-admin-messages"><?= json_encode($mediaAdminMessages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
     
