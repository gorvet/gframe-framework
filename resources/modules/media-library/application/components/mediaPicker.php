<div class="modal fade" id="gframe-media-picker" tabindex="-1" aria-labelledby="gframe-media-picker-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="gframe-media-picker-title">Seleccionar archivo</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form class="row g-2 mb-3" data-media-picker-filter>
                    <div class="col"><input class="form-control" name="search" placeholder="Buscar archivos"></div>
                    <div class="col-12 col-md-4"><select class="form-select" name="kind"><option value="all">Todos los tipos</option><option value="images">Imágenes</option><option value="videos">Videos</option><option value="audios">Audio</option><option value="docs">Documentos</option></select></div>
                    <div class="col-12 col-md-3"><label class="visually-hidden" for="media-picker-month">Mes</label><input class="form-control" id="media-picker-month" name="ym" type="month"></div>
                    <div class="col-12 col-md-auto"><label class="btn btn-outline-primary mb-0">Subir archivo<input class="d-none" type="file" data-media-picker-upload></label></div>
                </form>
                <form class="row g-2 mb-3" data-media-picker-hotlink><div class="col"><label class="visually-hidden" for="media-picker-hotlink-url">URL HTTPS del archivo</label><input class="form-control" id="media-picker-hotlink-url" name="media_url" type="url" placeholder="https://ejemplo.com/imagen.jpg" required></div><div class="col-12 col-md-3"><label class="visually-hidden" for="media-picker-hotlink-name">Nombre opcional</label><input class="form-control" id="media-picker-hotlink-name" name="name" maxlength="255" placeholder="Nombre opcional"></div><div class="col-12 col-md-auto"><button class="btn btn-outline-primary w-100" type="submit">Añadir desde URL</button></div></form>
                <div class="row g-3" data-media-picker-grid></div>
                <p class="text-body-secondary text-center d-none" data-media-picker-empty>No hay archivos para mostrar.</p>
                <div class="d-flex align-items-center justify-content-between mt-3" data-media-picker-pagination><button class="btn btn-sm btn-outline-secondary" type="button" data-media-picker-prev>Anterior</button><span class="small text-body-secondary" data-media-picker-page></span><button class="btn btn-sm btn-outline-secondary" type="button" data-media-picker-next>Siguiente</button></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" type="button" data-media-picker-confirm>Usar selección</button>
            </div>
        </div>
    </div>
</div>
