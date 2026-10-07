!(function($) {
  "use strict";

  /**
   * MediaLibrary (core)
   * - Lista / filtra / pagina / sube imágenes
   * - No edita ni borra (eso va en EditMedia.js)
   * - Instanciable y reutilizable (picker, featured image, etc.)
   *
   * Requiere:
   * - jQuery
   * - site_url global
   * - (opcional) showSpinner, alertToast, swalAlert, successError, ajaxError
   */

  function _uid(prefix){
    return (prefix || 'ml_') + Math.random().toString(16).slice(2);
  }

  function _thumbFromUrl(mediaUrl, size){
    if (!mediaUrl) return '';
    // soporta querystring
    var q = '';
    var u = mediaUrl;
    var qpos = u.indexOf('?');
    if (qpos !== -1) { q = u.slice(qpos); u = u.slice(0, qpos); }

    var dot = u.lastIndexOf('.');
    if (dot === -1) return mediaUrl;
    return u.slice(0, dot) + '-' + size + u.slice(dot) + q;
  }

  function _absUrl(path){
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    // site_url ya viene con slash final en tu proyecto, pero por si acaso:
    if (typeof site_url === 'string') {
      if (site_url.endsWith('/') && path.startsWith('/')) return site_url + path.slice(1);
      return site_url + path;
    }
    return path;
  }

  function _safeToastError(title){
    if (typeof alertToast === 'function') {
      alertToast({ icon: 'error', title: title || 'Error' });
      return;
    }
    console.error(title || 'Error');
  }

  function _safeToastSuccess(title){
    if (typeof alertToast === 'function') {
      alertToast({ icon: 'success', title: title || 'OK' });
      return;
    }
    console.log(title || 'OK');
  }

  function _normSource(v){
    v = (v || '').toString().trim().toLowerCase();
    if (!v || v === 'all') return 'all';
    return /^[a-z0-9_-]{1,32}$/.test(v) ? v : 'all';
  }


// saveSource (destino de guardado) NO debe aceptar "all".
// - '' / null => ''
// - 'all'      => ''
// - resto      => slug safe (a-z0-9_-)
function _normSaveSource(v){
  v = (v || '').toString().trim().toLowerCase();
  if (!v || v === 'all') return '';
  return /^[a-z0-9_-]{1,32}$/.test(v) ? v : '';
}


function _normYm(v){
    v = (v || '').toString().trim();
    if (!v || v === 'all') return 'all';
    return /^\d{4}-\d{2}$/.test(v) ? v : 'all';
  }

  function _formatMb(v){
    var n = parseFloat(v);
    if (!isFinite(n) || isNaN(n) || n < 0) n = 0;
    return n.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
  }


  // =========================
  // Kind config / validation
  // =========================
  function _normKind(v){
    v = (v || '').toString().trim().toLowerCase();
    if (!v) return 'all';
    if (v === 'all' || v === '*') return 'all';
    if (v === 'image' || v === 'img') return 'images';
    if (v === 'file' || v === 'files' || v === 'document' || v === 'doc' || v === 'docs') return 'docs';
    if (v === 'audio' || v === 'audios' || v === 'sound') return 'audios';
    if (v === 'video' || v === 'videos') return 'videos';
    if (v === 'images' || v === 'docs' || v === 'audios' || v === 'videos') return v;
    return 'all';
  }

  function resolveTokensForm(candidate){
    var $candidate = $();

    if (candidate && candidate.jquery) {
      $candidate = candidate.first();
    } else if (candidate) {
      $candidate = $(candidate).first();
    }

    if ($candidate.length) return $candidate;

    var $byId = $('#tokens').first();
    if ($byId.length) return $byId;

    var $byFlag = $('form[data-ml-tokens]').first();
    if ($byFlag.length) return $byFlag;

    return $();
  }

  function MediaLibrary(options){
    options = options || {};
    this.id = _uid('ml_');

    this.$mount  = $(options.mount);
    this.$tokens = resolveTokensForm(options.tokens);

    this.uiRoot = options.uiRoot || null;
    this.$uiRoot = null;

    this.mode = options.mode || 'manage'; // manage | picker
    this.syncUrlEnabled = (options.syncUrlEnabled !== undefined) ? !!options.syncUrlEnabled : (this.mode === 'manage');
    this.listenPopState = (options.listenPopState !== undefined) ? !!options.listenPopState : this.syncUrlEnabled;

    this.endpoints = $.extend({
      list: (typeof site_url === 'string') ? site_url + 'ajax/admin/media/list' : 'ajax/admin/media/list',
      upload: (typeof site_url === 'string') ? site_url + 'ajax/admin/media/upload' : 'ajax/admin/media/upload'
    }, options.endpoints || {});

    // Destino de guardado (upload). OJO: no tiene nada que ver con el filtro 'source' del listado.
    // Se puede setear por options.saveSource o por data-ml-save-source en el mount.
    this.saveSource = _normSaveSource(options.saveSource || this.$mount.attr('data-ml-save-source') || '');
    var rawTenantID = (options.tenantID !== undefined && options.tenantID !== null && options.tenantID !== '')
      ? options.tenantID
      : (this.$mount.attr('data-ml-tenant-id') || $('#m_tenant_id').val() || 0);
    this.tenantID = parseInt(rawTenantID, 10) || 0;
    if (this.tenantID < 0) this.tenantID = 0;

    // Límite por archivo (cliente). En MediaField/MediaPicker se setea con setUploadMaxMB()
    this.uploadMaxMB = parseFloat(options.uploadMaxMB || this.$mount.attr('data-ml-max-mb') || this.$mount.attr('data-ml-upload-max-mb') || 0) || 0;

    this.state = {
      source: 'all',
      ym: 'all',
      q: '',
      page: 1,
      kind: 'all'
    };

    // kind: options.kind > data-ml-kind > URL(kind) (si no está 'locked') > all
    this._kindLocked = false;
    var domKind = this.$mount.attr('data-ml-kind');
    var optKind = (options.kind !== undefined && options.kind !== null && options.kind !== '') ? options.kind : null;
    var initKind = (optKind !== null) ? optKind : (domKind ? domKind : null);
    if (initKind !== null) this._kindLocked = true;
    this.state.kind = _normKind(initKind);


    this._debounceTimer = null;

    if (!this.$mount.length) {
      throw new Error('MediaLibrary: mount no existe');
    }
    if (!this.$tokens.length) {
      // tokens puede ser un <form> vacío en picker si no hace falta, pero en tu caso sí hace falta.
      // Igual no rompemos, pero avisamos.
      console.warn('MediaLibrary: tokens no existe (continuo igual)');
    }

    this._bindCoreUi();
    this._bindGlobalPagination();
    this._bindPopState();

    // autoload opcional
    if (options.autoload) {
      this.initFromUrlOrDom();
      this.load(this.state.page, { push: false, makeActive: true });
    }
  }

  // =========================
  // Static: instancia activa (para compat con paginación legacy)
  // =========================
  MediaLibrary._active = null;
  MediaLibrary.setActive = function(inst){
    MediaLibrary._active = inst;
  };
  MediaLibrary.getActive = function(){
    return MediaLibrary._active;
  };
  MediaLibrary.resolveTokensForm = resolveTokensForm;

  // =========================
  // Helpers instancia
  // =========================
  MediaLibrary.prototype._refreshTokens = function(){
    this.$tokens = resolveTokensForm(this.$tokens);
    return this.$tokens;
  };
  
  MediaLibrary.prototype._getUiRoot = function(){
    // Root para eventos y lectura/escritura de filtros.
    // En muchas UIs el mount es solo la lista, y los filtros viven en un contenedor padre.
    if (this.$uiRoot && this.$uiRoot.length) return this.$uiRoot;

    // Si el usuario lo pasó por options.uiRoot (selector o elemento), úsalo
    if (this.uiRoot) {
      try {
        var $u = (this.uiRoot instanceof $) ? this.uiRoot : $(this.uiRoot);
        if ($u.length) { this.$uiRoot = $u; return $u; }
      } catch(e){}
    }

    var sel = '#media-source-filter, #media-date-filter, #media-search-input, [data-ml-filter]';
    var $c = this.$mount;
    var tries = 0;

    while ($c && $c.length && tries < 12) {
      if ($c.find(sel).length) { this.$uiRoot = $c; return $c; }
      $c = $c.parent();
      tries++;
    }

    // fallback seguro
    this.$uiRoot = this.$mount;
    return this.$uiRoot;
  };

MediaLibrary.prototype.setMode = function(mode){
    this.mode = mode || this.mode;
  };


  // saveSource: dónde se guarda cuando subes. NO afecta el filtro del listado.
  MediaLibrary.prototype.setSaveSource = function(src){
    this.saveSource = _normSaveSource(src);
    return this;
  };

  MediaLibrary.prototype.getSaveSource = function(){
    return _normSaveSource(this.saveSource);
  };


  // uploadMaxMB: límite por archivo (cliente). Backend debe validar igual.
  MediaLibrary.prototype.setUploadMaxMB = function(mb){
    var n = parseFloat(mb);
    this.uploadMaxMB = (isNaN(n) || n < 0) ? 0 : n;
    try { this._updateUploadMaxMbValue(); } catch(e){}
    return this;
  };

  MediaLibrary.prototype._updateUploadMaxMbValue = function(){
    if (!this._uploader || !this._uploader.maxMbEl || !this._uploader.maxMbEl.length) return;
    var n = parseFloat(this.uploadMaxMB);
    if (isNaN(n) || n <= 0) {
      this._uploader.maxMbEl.text('');
      return;
    }
    // Solo ponemos el valor (sin tocar el resto del dropzone)
    var txt = (n % 1 === 0) ? String(parseInt(n, 10)) : String(n);
    this._uploader.maxMbEl.text(txt);
  };


  MediaLibrary.prototype._bindGlobalPagination = function(){
    // Compatibilidad con pagination() que llama a fetchDataForPage(page)
    if (typeof window.fetchDataForPage !== 'function' || !window.fetchDataForPage.__mediaLibrary) {
      window.fetchDataForPage = function(page){
        var inst = MediaLibrary.getActive();
        if (!inst) return;
        return inst.load(page, { push: true, makeActive: true });
      };
      window.fetchDataForPage.__mediaLibrary = true;
    }
  };

  MediaLibrary.prototype._bindPopState = function(){
    var self = this;
    if (!this.listenPopState) return;
    if (MediaLibrary.__popBound) return;

    window.addEventListener('popstate', function(){
      var inst = MediaLibrary.getActive();
      if (!inst || !inst.syncUrlEnabled) return;
      inst.readUrlToState();
      inst.load(inst.state.page, { push: false, makeActive: true });
    });

    MediaLibrary.__popBound = true;
  };

  MediaLibrary.prototype._bindCoreUi = function(){
    var self = this;
    var $ui = this._getUiRoot();

    $ui.on('click.mediaLibrary', '[data-ml-pagination] .linkeable, [data-ml-pagination] .next, [data-ml-pagination] .prev', function(e){
      e.preventDefault();
      e.stopPropagation();
      var $link = $(this);
      var page = $link.hasClass('next') ? self.state.page + 1 : ($link.hasClass('prev') ? self.state.page - 1 : parseInt($link.text(), 10));
      if (isFinite(page) && page >= 1) self.load(page, { push: true, makeActive: true });
    });

    // filtros: change (ids clásicos + data-ml-filter)
    $ui.on('change.mediaLibrary', '#media-source-filter, #media-date-filter, #media-kind-filter, [data-ml-filter="source"], [data-ml-filter="ym"], [data-ml-filter="kind"]', function(){
      self.state.page = 1;
      self.load(1, { push: true, makeActive: true });
    });

    // search input: debounce
    $ui.on('input.mediaLibrary', '#media-search-input, [data-ml-filter="q"], [data-ml-filter="search"]', function(){
      clearTimeout(self._debounceTimer);
      self._debounceTimer = setTimeout(function(){
        self.state.page = 1;
        self.load(1, { push: false, makeActive: true });
      }, 300);
    });

    // Enter en search => push
    $ui.on('keydown.mediaLibrary', '#media-search-input, [data-ml-filter="q"], [data-ml-filter="search"]', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        self.state.page = 1;
        self.load(1, { push: true, makeActive: true });
      }
    });

    // botón buscar (si existe)
    $ui.on('click.mediaLibrary', '#media-search-btn, [data-ml-action="search"]', function(e){
      e.preventDefault();
      self.state.page = 1;
      self.load(1, { push: true, makeActive: true });
    });

    // botón limpiar filtros
    $ui.on('click.mediaLibrary', '#media-clear-filters-btn, [data-ml-action="clear-filters"]', function(e){
      e.preventDefault();
      self.resetFilters();
      self.load(1, { push: true, makeActive: true });
    });

    // click en item => evento desacoplado (picker o editMedia lo manejan)
    this.$mount.on('click.mediaLibrary', '.m-list-card', function(e){
      e.preventDefault();

      var $el = $(this);
      var item = self.normalizeItem($el);

      // evento para plugins (EditMedia / MediaPicker)
      self.$mount.trigger('ml:itemClick', [item, self]);

      if (typeof self.onItemClick === 'function') {
        self.onItemClick(item, e, self);
      }
    });
  };

  MediaLibrary.prototype.updateCapacityInfo = function(capacityPayload){
    var $wrap = $('#mediaCapacityInfo');
    if (!$wrap.length || !capacityPayload || typeof capacityPayload !== 'object') return;

    var data = capacityPayload.data ? capacityPayload.data : capacityPayload;
    if (!data || typeof data !== 'object') return;

    var usedMb = parseFloat(data.used_mb || 0);
    var limitMb = parseFloat(data.limit_mb || 0);
    var isNear = !!data.is_near_limit;
    var isOver = !!data.is_over_limit;
    var showUpgrade = !!data.show_upgrade;

    var $used = $('#mediaCapacityUsed');
    if ($used.length) $used.text(_formatMb(usedMb));

    var $plan = $('#mediaCapacityPlan');
    if ($plan.length) $plan.text(_formatMb(limitMb));

    var $upgrade = $('#mediaCapacityUpgrade');
    if ($upgrade.length) $upgrade.toggleClass('d-none', !showUpgrade);

    $wrap.removeClass('text-warning text-danger');
    if (isOver) $wrap.addClass('text-danger');
    else if (isNear) $wrap.addClass('text-warning');
  };

  // mediaLibrary.js
MediaLibrary.prototype.normalizeItem = function($el){
  var id = parseInt($el.data('media-id'), 10);
  if (!id) {
    var rid = ($el.attr('id') || '').toString();
    if (rid.indexOf('mID_') === 0) id = parseInt(rid.slice(4), 10) || 0;
  }

  var mediaUrl   = ($el.data('media-url') || '').toString();
  var thumbSmall = ($el.data('media-thumb-small') || '').toString();

  var name = ($el.data('media-name') || '').toString();
  var mime = ($el.data('media-mime') || '').toString();

  // ✅ NUEVO: leer del backend
  var type = ($el.data('media-type') || '').toString();     // images|docs|audio|video|...
  var ext  = ($el.data('media-ext') || '').toString();      // pdf|docx|mp3|...
  var alt  = ($el.data('media-alt') || '').toString();      // alt_text

  // ✅ isImage debe priorizar type si viene
  var isImage = (type === 'images') || (mime && mime.indexOf('image/') === 0);

  var obj = {
    id: id || 0,
    media_url: mediaUrl,
    url: _absUrl(mediaUrl),

    // ✅ NUEVO
    type: type,
    ext: ext,
    alt_text: alt,

    thumb_small: isImage ? (thumbSmall ? _absUrl(thumbSmall) : _absUrl(_thumbFromUrl(mediaUrl, 'small'))) : '',
    thumb_xsmall: isImage ? _absUrl(_thumbFromUrl(mediaUrl, 'xsmall')) : '',
    thumb_medium: isImage ? _absUrl(_thumbFromUrl(mediaUrl, 'medium')) : '',
    name: name,
    mime_type: mime,
    is_image: isImage
  };

  if (obj.is_image) {
    var tX = ($el.data('media-thumb-xsmall') || '').toString();
    var tM = ($el.data('media-thumb-medium') || '').toString();
    if (tX) obj.thumb_xsmall = _absUrl(tX);
    if (tM) obj.thumb_medium = _absUrl(tM);
  }

  return obj;
};

  // =========================
  // State <-> UI / URL
  // =========================
  MediaLibrary.prototype.readUiToState = function(){
    var $ui = this._getUiRoot();

    // soporte: ids clásicos y data-ml-filter
    var $src = $ui.find('[data-ml-filter="source"]').first();
    if (!$src.length) $src = $ui.find('#media-source-filter').first();

    var $ym = $ui.find('[data-ml-filter="ym"]').first();
    if (!$ym.length) $ym = $ui.find('#media-date-filter').first();

    var $q = $ui.find('[data-ml-filter="q"], [data-ml-filter="search"]').first();
    if (!$q.length) $q = $ui.find('#media-search-input').first();

    var $k = $ui.find('[data-ml-filter="kind"]').first();
    if (!$k.length) $k = $ui.find('#media-kind-filter').first();

    if ($src.length) this.state.source = _normSource($src.val());
    else this.state.source = 'all';
    if ($ym.length)  this.state.ym     = _normYm($ym.val());
    if ($q.length)   this.state.q      = ($q.val() || '').toString().trim();
    if (!this._kindLocked && $k.length) this.state.kind = _normKind($k.val());
  };

  MediaLibrary.prototype.applyStateToUi = function(){
    var $ui = this._getUiRoot();

    var $src = $ui.find('[data-ml-filter="source"]').first();
    if (!$src.length) $src = $ui.find('#media-source-filter').first();

    var $ym = $ui.find('[data-ml-filter="ym"]').first();
    if (!$ym.length) $ym = $ui.find('#media-date-filter').first();

    var $q = $ui.find('[data-ml-filter="q"], [data-ml-filter="search"]').first();
    if (!$q.length) $q = $ui.find('#media-search-input').first();
    var $k = $ui.find('[data-ml-filter="kind"]').first();
    if (!$k.length) $k = $ui.find('#media-kind-filter').first();

    if ($src.length) $src.val(this.state.source || 'all');
    if ($ym.length)  $ym.val(this.state.ym || 'all');
    if ($k.length && !this._kindLocked) $k.val(this.state.kind || 'all');

    // si el usuario está escribiendo ahora mismo, no le pelees el foco
    if ($q.length) {
      var active = document.activeElement;
      if (!active || active !== $q.get(0)) {
        $q.val(this.state.q || '');
      }
    }
  };

  MediaLibrary.prototype.readUrlToState = function(){
    if (!this.syncUrlEnabled) return;

    var params = new URLSearchParams(window.location.search);

    // kind (solo si no viene fijado por options/data-ml-kind)
    var k = _normKind(params.get('kind'));
    if (!this._kindLocked && k) this.state.kind = k;

    this.state.source = _normSource(params.get('source'));
    this.state.ym     = _normYm(params.get('ym'));
    this.state.q      = (params.get('q') || '').toString().trim();

    var p = parseInt(params.get('page') || '1', 10) || 1;
    this.state.page = (p < 1) ? 1 : p;

    this.applyStateToUi();
  };

  
  MediaLibrary.prototype.setKind = function(kind, opts){
    opts = opts || {};
    this.state.kind = _normKind(kind);
    this._kindLocked = true;

    // Upload UI: no se modifica desde aquí (lo maneja la UI externa)

    // por defecto recarga (compat); en picker podemos setear sin recargar
    if (opts.reload === false) return;

    return this.load(1, { push: false, makeActive: true });
  };

  // compat: algunos módulos viejos llaman refresh(page, push)
  MediaLibrary.prototype.refresh = function(page, push){
    var p = parseInt(page, 10) || 1;
    return this.load(p, { push: !!push, makeActive: true });
  };

MediaLibrary.prototype.syncUrl = function(push){
    if (!this.syncUrlEnabled) return;

    var u = new URL(window.location.href);

    if ((this.state.page || 1) !== 1) u.searchParams.set('page', String(this.state.page || 1));
    else u.searchParams.delete('page');

    if (this.state.source !== 'all') u.searchParams.set('source', this.state.source);
    else u.searchParams.delete('source');

    if (this.state.ym !== 'all') u.searchParams.set('ym', this.state.ym);
    else u.searchParams.delete('ym');

    if (this.state.q) u.searchParams.set('q', this.state.q);
    else u.searchParams.delete('q');


    if (this.state.kind && this.state.kind !== 'all') u.searchParams.set('kind', this.state.kind);
    else u.searchParams.delete('kind');

    var newUrl = u.pathname + (u.search ? u.search : '');

    var st = { source: this.state.source, ym: this.state.ym, q: this.state.q, page: this.state.page, kind: this.state.kind };
    if (push) history.pushState(st, '', newUrl);
    else history.replaceState(st, '', newUrl);
  };

  MediaLibrary.prototype.initFromUrlOrDom = function(){
    if (!this.syncUrlEnabled) {
      // sin URL: lee UI si existe
      this.readUiToState();
      return;
    }

    var params = new URLSearchParams(window.location.search);
    var hasAny = params.has('source') || params.has('ym') || params.has('q') || params.has('page') || params.has('kind');

    // si URL trae kind y no está locked, lo aplicamos aquí también
    if (!this._kindLocked && params.has('kind')) {
      this.state.kind = _normKind(params.get('kind'));
    }

    if (hasAny) {
      this.readUrlToState();
    } else {
      this.readUiToState();
      this.syncUrl(false);
    }
  };

  MediaLibrary.prototype.resetFilters = function(){
    this.state.source = 'all';
    this.state.ym = 'all';
    this.state.q = '';
    this.state.page = 1;

    var $ui = this._getUiRoot();

    var $src = $ui.find('[data-ml-filter="source"]').first();
    if (!$src.length) $src = $ui.find('#media-source-filter').first();

    var $ym = $ui.find('[data-ml-filter="ym"]').first();
    if (!$ym.length) $ym = $ui.find('#media-date-filter').first();

    var $q = $ui.find('[data-ml-filter="q"], [data-ml-filter="search"]').first();
    if (!$q.length) $q = $ui.find('#media-search-input').first();
    var $k = $ui.find('[data-ml-filter="kind"]').first();
    if (!$k.length) $k = $ui.find('#media-kind-filter').first();

    if ($src.length) $src.val('all');
    if ($ym.length)  $ym.val('all');
    if ($q.length)   $q.val('');
    if ($k.length && !this._kindLocked) $k.val('all');
    if (!this._kindLocked) this.state.kind = 'all';
  };

  // =========================
  // Load list (AJAX)
  // =========================
  MediaLibrary.prototype._buildListData = function(page){
    var $tokens = this._refreshTokens();
    var base = $tokens.length ? $tokens.serialize() : '';
    var p = parseInt(page, 10) || 1;
    if (p < 1) p = 1;

    // UI -> state
    this.readUiToState();
    this.state.page = p;

    var data = base ? (base + '&') : '';
    data += 'page=' + encodeURIComponent(this.state.page);
    data += '&fragment=' + (this.mode === 'picker' ? 'picker' : 'library');

    if (this.state.source && this.state.source !== 'all') data += '&source=' + encodeURIComponent(this.state.source);
    if (this.state.ym && this.state.ym !== 'all')         data += '&ym='     + encodeURIComponent(this.state.ym);
    if (this.state.q)                                     data += '&q='      + encodeURIComponent(this.state.q);
    if (this.state.kind && this.state.kind !== 'all')      data += '&kind='   + encodeURIComponent(this.state.kind);
    if (this.tenantID > 0)                                 data += '&tenant_id=' + encodeURIComponent(this.tenantID);

    return data;
  };

  MediaLibrary.prototype.load = function(page, opts){
    var self = this;
    opts = opts || {};

    // en cuanto la usan, se vuelve activa (para fetchDataForPage)
    if (opts.makeActive !== false) MediaLibrary.setActive(this);

    // construye data (esto lee la UI y actualiza state)
    var data = this._buildListData(page);

    // url sync (después de leer UI/state)
    var push = (opts.push === undefined) ? true : !!opts.push;
    if (this.syncUrlEnabled) this.syncUrl(push);

    return $.ajax({
      type: 'POST',
      url: this.endpoints.list,
      data: data,
      dataType: 'json',
      success: function(response){
        if (response && response.status === 'success') {
          self.$mount.html(response.html || '');
          if (response.meta && response.meta.max_upload_bytes) {
            self.setUploadMaxMB(Number(response.meta.max_upload_bytes) / 1048576);
          }
          self.updateCapacityInfo(response.capacity || null);

          // rehidrata filtros (si el backend no marcó selected)
          try { self.applyStateToUi(); } catch(e) {}

          // clamp page si backend lo hizo
          if (response.meta && response.meta.page) {
            var realPage = parseInt(response.meta.page, 10) || self.state.page;
            if (realPage !== self.state.page) {
              self.state.page = realPage;
              if (self.syncUrlEnabled) self.syncUrl(false);
            }
          }

          self.$mount.trigger('ml:render', [response, self]);
        } else {
          var msg = (response && response.message) ? response.message : 'Error al cargar medios';
          var title = msg;
          if (typeof successError === 'function') {
            try { title = successError(response.message, response.code).title; } catch(e){}
          }
          _safeToastError(title);
        }
      },
      error: function(xhr, status, error){
        var title = (typeof ajaxError === 'function') ? ajaxError(status, error).title : ('Error: ' + status);
        _safeToastError(title);
      }
    });
  };

  MediaLibrary.prototype.reload = function(opts){
    opts = opts || {};
    return this.load(this.state.page || 1, opts);
  };

  // =========================
  // Upload (opcional)
  // =========================
  MediaLibrary.prototype.attachUpload = function(cfg){
    var self = this;
    cfg = cfg || {};

    // selectors / elements (auto-detect por data-ml-*; sin necesidad de ids)
    // Root: si el mount está dentro de un modal, el root será el modal; si no, document.
    var $root = cfg.root ? $(cfg.root) : self.$mount.closest('.modal');
    if (!$root.length) $root = $(document);

    var $uploadScope = $root.find('[data-ml-upload]').first();

    // dropzone
    var $dropZone = cfg.dropZone ? $(cfg.dropZone)
      : ($uploadScope.length ? $uploadScope.find('[data-ml-dropzone]').first() : $());
    if (!$dropZone.length && $uploadScope.length && $uploadScope.is('[data-ml-dropzone]')) $dropZone = $uploadScope;
    if (!$dropZone.length) $dropZone = $root.find('#drop-zone');
    if (!$dropZone.length) $dropZone = $('#drop-zone');

    // input file
    var $fileInput = cfg.fileInput ? $(cfg.fileInput)
      : ($uploadScope.length ? $uploadScope.find('[data-ml-file]').first() : $());
    if (!$fileInput.length) $fileInput = $root.find('#file-input');
    if (!$fileInput.length) $fileInput = $('#file-input');

    // botón seleccionar/subir
    var $uploadBtn = cfg.uploadButton ? $(cfg.uploadButton)
      : ($uploadScope.length ? $uploadScope.find('[data-ml-uploadbtn]').first() : $());
    if (!$uploadBtn.length) $uploadBtn = $root.find('#upload_media');
    if (!$uploadBtn.length) $uploadBtn = $('#upload_media');

    // hotlink URL (opcional)
    var $urlInput = cfg.urlInput ? $(cfg.urlInput)
      : $root.find('[data-ml-url-input]').first();
    if (!$urlInput.length) $urlInput = $root.find('#mp-hotlink-url, #hotlink-url').first();

    var $urlName = cfg.urlName ? $(cfg.urlName)
      : $root.find('[data-ml-url-name]').first();
    if (!$urlName.length) $urlName = $root.find('#mp-hotlink-name, #hotlink-name').first();

    var $urlAddBtn = cfg.urlButton ? $(cfg.urlButton)
      : $root.find('[data-ml-url-add]').first();
    if (!$urlAddBtn.length) $urlAddBtn = $root.find('#mp-hotlink-add, #hotlink-add').first();


    // refs del uploader (sin tocar el markup del dropzone)
    self._uploader = self._uploader || {};
    self._uploader.dropZone  = $dropZone;
    self._uploader.fileInput = $fileInput;
    self._uploader.buttonEl  = $uploadBtn;
    self._uploader.buttonText = ($uploadBtn.length ? ($uploadBtn.text() || '') : '');
    self._uploader.urlInput = $urlInput;
    self._uploader.urlName = $urlName;
    self._uploader.urlButtonEl = $urlAddBtn;
    self._uploader.urlButtonText = ($urlAddBtn.length ? ($urlAddBtn.text() || '') : '');

    // No restringimos tipos en el cliente: el selector muestra todos (*).
    // La validación real de formatos la hace el backend.
    try { $fileInput.removeAttr('accept'); } catch(e){}

    // Placeholder opcional para mostrar el máximo de MB por archivo (solo pone el valor, no modifica estructura):
    // Ejemplo en tu HTML del dropzone: <span data-ml-max-mb-value></span>
    self._uploader.maxMbEl = $dropZone.find('[data-ml-max-mb-value], [data-ml-maxmb-value], .ml-max-mb-value').first();
    if (!self._uploader.maxMbEl.length) self._uploader.maxMbEl = $root.find('[data-ml-max-mb-value], [data-ml-maxmb-value], .ml-max-mb-value').first();
    try { self._updateUploadMaxMbValue(); } catch(e){}

    // dropzone: no se tocan clases/markup; solo eventos.

    // toggle panel (opcional)
    var $toggleBtn = cfg.addPanelButton ? $(cfg.addPanelButton) : $root.find('[data-ml-toggle-upload]').first();
    if (!$toggleBtn.length) $toggleBtn = $root.find('#add_media');
    if (!$toggleBtn.length) $toggleBtn = $('#add_media');

    var $wrap = cfg.addPanelWrap ? $(cfg.addPanelWrap) : $root.find('[data-ml-upload-wrap]').first();
    if (!$wrap.length) $wrap = $root.find('#add_media_wrap');
    if (!$wrap.length) $wrap = $('#add_media_wrap');

    var $close = cfg.addPanelClose ? $(cfg.addPanelClose) : $root.find('[data-ml-close-upload]').first();
    if (!$close.length) $close = $root.find('#close_media_wrap');
    if (!$close.length) $close = $('#close_media_wrap');

    if ($toggleBtn.length && $wrap.length) {
      $toggleBtn.off('click.mediaLibraryAddPanel').on('click.mediaLibraryAddPanel', function(){
        $wrap.toggleClass('d-none');
      });
    }
    if ($close.length && $wrap.length) {
      $close.off('click.mediaLibraryAddPanel').on('click.mediaLibraryAddPanel', function(e){
        e.preventDefault();
        $wrap.addClass('d-none');
      });
    }

    // subir
    function _addFiles(files){
      var alertOptions = { icon: 'error', title: '' };
      var msg = (files && files.length > 1) ? 'Subiendo archivos' : 'Subiendo archivo';

      var anyOk = false;
      var pending = 0;

      if (!files || !files.length) return;

      for (var i = 0; i < files.length; i++) {
        var file = files[i];
        if (!file) continue;

        // Límite de tamaño por archivo (cliente). Backend valida igual.
        var maxMB = parseFloat(self.uploadMaxMB) || 0;
        if (maxMB > 0) {
          var maxBytes = maxMB * 1024 * 1024;
          if (file.size && file.size > maxBytes) {
            if (typeof alertToast === 'function') {
              alertToast({ icon: 'error', title: file.name + ' excede ' + (maxMB % 1 === 0 ? String(parseInt(maxMB,10)) : String(maxMB)) + ' MB' });
            } else {
              _safeToastError(file.name + ' excede ' + (maxMB % 1 === 0 ? String(parseInt(maxMB,10)) : String(maxMB)) + ' MB');
            }
            continue;
          }
        }


        pending++;
        if (typeof showSpinner === 'function' && $uploadBtn.length) {
          showSpinner($uploadBtn, msg, true);
        }

        var formData = new FormData();
        var $tokens = self._refreshTokens();

        // tokens -> FormData
        if ($tokens.length) {
          ($tokens.serializeArray() || []).forEach(function(x){
            formData.append(x.name, x.value);
          });
        }

        formData.append('file', file);
        formData.append('kind', (self.state.kind || 'all'));
        if (self.tenantID > 0) formData.append('tenant_id', String(self.tenantID));

        // destino de guardado (NO usar el filtro del listado). Si no está definido, no lo mandamos.
        var saveSrc = (typeof self.getSaveSource === 'function') ? self.getSaveSource() : _normSaveSource(self.saveSource);
        if (saveSrc) formData.append('source', saveSrc);



        (function(fileName){

        $.ajax({
          url: self.endpoints.upload,
          type: 'POST',
          data: formData,
          contentType: false,
          processData: false,
          dataType: 'json',
          success: function(resp){
            pending--;
            if (typeof showSpinner === 'function' && $uploadBtn.length) {
              showSpinner($uploadBtn, (self._uploader && self._uploader.buttonText) ? self._uploader.buttonText : ($uploadBtn.text() || 'Subir'), pending > 0);
            }

            if (resp && resp.status === 'success') {
              anyOk = true;
              _safeToastSuccess('El archivo <strong>' + fileName + '</strong> se ha guardado.');
            } else {
              if (resp && resp.code === 'disk_quota_exceeded') {
                var usedMb = parseFloat((resp.data && resp.data.used_mb) ? resp.data.used_mb : 0) || 0;
                var limitMb = parseFloat((resp.data && resp.data.limit_mb) ? resp.data.limit_mb : 0) || 0;
                var projectedMb = parseFloat((resp.data && resp.data.projected_mb) ? resp.data.projected_mb : usedMb) || usedMb;
                var ratio = (limitMb > 0) ? ((usedMb / limitMb) * 100) : 0;
                var isOver = (limitMb > 0) && (usedMb >= limitMb);
                var isNear = !isOver && ratio >= 90;
                self.updateCapacityInfo({
                  data: {
                    used_mb: usedMb,
                    limit_mb: limitMb,
                    is_near_limit: isNear,
                    is_over_limit: isOver,
                    show_upgrade: ((limitMb > 0) && (projectedMb >= limitMb)) || isNear || isOver
                  }
                });
              }
              var title = (resp && resp.message) ? resp.message : 'Error al subir';
              if (typeof successError === 'function') {
                try { title = successError(resp.message, resp.code).title; } catch(e){}
              }
              _safeToastError(title);
            }

            if (pending === 0 && anyOk) {
              self.resetFilters();
              if (self.syncUrlEnabled) self.syncUrl(false);
              self.load(1, { push: false, makeActive: true });
            }
          },
          error: function(xhr, status, error){
            pending--;
            if (typeof showSpinner === 'function' && $uploadBtn.length) {
              showSpinner($uploadBtn, (self._uploader && self._uploader.buttonText) ? self._uploader.buttonText : ($uploadBtn.text() || 'Subir'), pending > 0);
            }
            var title = (typeof ajaxError === 'function') ? ajaxError(status, error).title : ('Error: ' + status);
            _safeToastError(title);

            if (pending === 0 && anyOk) {
              self.resetFilters();
              if (self.syncUrlEnabled) self.syncUrl(false);
              self.load(1, { push: false, makeActive: true });
            }
          }
        });
        })(file.name);
      }
    }

    function _addFromUrl(){
      var rawUrl = $urlInput.length ? (($urlInput.val() || '') + '').trim() : '';
      var customName = $urlName.length ? (($urlName.val() || '') + '').trim() : '';
      if (!rawUrl) {
        _safeToastError('Debes indicar una URL valida.');
        return;
      }

      var payload = {};
      var $tokens = self._refreshTokens();
      if ($tokens.length) {
        ($tokens.serializeArray() || []).forEach(function(x){
          if (!x || !x.name) return;
          payload[x.name] = x.value;
        });
      }

      payload.media_url = rawUrl;
      payload.kind = (self.state.kind || 'all');
      if (self.tenantID > 0) payload.tenant_id = String(self.tenantID);

      var saveSrc = (typeof self.getSaveSource === 'function') ? self.getSaveSource() : _normSaveSource(self.saveSource);
      if (saveSrc) payload.source = saveSrc;
      if (customName) payload.name = customName;

      if ($urlAddBtn.length) {
        if (typeof showSpinner === 'function') {
          showSpinner($urlAddBtn, 'Anadiendo...', true);
        } else {
          $urlAddBtn.prop('disabled', true);
        }
      }

      $.ajax({
        url: self.endpoints.upload,
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function(resp){
          if ($urlAddBtn.length) {
            if (typeof showSpinner === 'function') {
              showSpinner($urlAddBtn, (self._uploader && self._uploader.urlButtonText) ? self._uploader.urlButtonText : ($urlAddBtn.text() || 'Anadir'), false);
            } else {
              $urlAddBtn.prop('disabled', false);
            }
          }

          if (resp && resp.status === 'success') {
            _safeToastSuccess('El medio externo se ha guardado.');
            if ($urlInput.length) $urlInput.val('');
            if ($urlName.length) $urlName.val('');

            // Volver a biblioteca para seleccionar lo insertado.
            if (typeof bootstrap !== 'undefined') {
              var libraryTabBtn = $root.find('[data-bs-target="#mp-library"]').first();
              if (libraryTabBtn.length) {
                bootstrap.Tab.getOrCreateInstance(libraryTabBtn.get(0)).show();
              }
            }

            self.resetFilters();
            if (self.syncUrlEnabled) self.syncUrl(false);
            self.load(1, { push: false, makeActive: true });
          } else {
            var title = (resp && resp.message) ? resp.message : 'Error al guardar la URL';
            if (typeof successError === 'function') {
              try { title = successError(resp.message, resp.code).title; } catch(e){}
            }
            _safeToastError(title);
          }
        },
        error: function(xhr, status, error){
          if ($urlAddBtn.length) {
            if (typeof showSpinner === 'function') {
              showSpinner($urlAddBtn, (self._uploader && self._uploader.urlButtonText) ? self._uploader.urlButtonText : ($urlAddBtn.text() || 'Anadir'), false);
            } else {
              $urlAddBtn.prop('disabled', false);
            }
          }
          var title = (typeof ajaxError === 'function') ? ajaxError(status, error).title : ('Error: ' + status);
          _safeToastError(title);
        }
      });
    }

    // dropzone (solo eventos; no se toca el HTML ni clases)
    if ($dropZone.length) {
      $dropZone.off('dragenter.mediaLibraryUpload').on('dragenter.mediaLibraryUpload', function(e){
        e.preventDefault();
      });

      $dropZone.off('dragover.mediaLibraryUpload').on('dragover.mediaLibraryUpload', function(e){
        e.preventDefault();
      });

      $dropZone.off('dragleave.mediaLibraryUpload').on('dragleave.mediaLibraryUpload', function(e){
        e.preventDefault();
      });

      $dropZone.off('drop.mediaLibraryUpload').on('drop.mediaLibraryUpload', function(e){
        e.preventDefault();
        _addFiles(e.originalEvent.dataTransfer.files);
      });

      // click dropzone => input
      $dropZone.off('click.mediaLibraryUpload').on('click.mediaLibraryUpload', function(e){
        // Evita recursión si el input vive dentro del dropzone (click bubble)
        if ($fileInput.length) {
          var inputEl = $fileInput.get(0);
          if (e && (e.target === inputEl || $(e.target).closest(inputEl).length)) return;
          e.preventDefault();
          e.stopPropagation();
          inputEl && inputEl.click();
        }
      });
    }

    // upload btn => input

    if ($uploadBtn.length && $fileInput.length) {
      $uploadBtn.off('click.mediaLibraryUpload').on('click.mediaLibraryUpload', function(e){
        e.preventDefault();
        e.stopPropagation();
        if ($fileInput.length) {
          var inputEl = $fileInput.get(0);
          inputEl && inputEl.click();
        }
      });
    }

    // input change
    if ($fileInput.length) {
      $fileInput.off('change.mediaLibraryUpload').on('change.mediaLibraryUpload', function(){
        _addFiles(this.files || []);
        this.value = '';
      });
    }

    if ($urlAddBtn.length) {
      $urlAddBtn.off('click.mediaLibraryHotlink').on('click.mediaLibraryHotlink', function(e){
        e.preventDefault();
        _addFromUrl();
      });
    }

    if ($urlInput.length) {
      $urlInput.off('keydown.mediaLibraryHotlink').on('keydown.mediaLibraryHotlink', function(e){
        if (e.key === 'Enter') {
          e.preventDefault();
          _addFromUrl();
        }
      });
    }

    return this;
  };

  // =========================
  // Auto-init (opcional)
  // =========================
  MediaLibrary.autoInit = function(){
  // Busca montajes declarativos.
  // Por defecto: auto-init en páginas (fuera de modals). En modals lo maneja MediaPicker.
  // Para desactivar: data-ml-auto="0" o data-ml-noauto
  $('[data-ml-mount]').each(function(){
    var $mount = $(this);
    if ($mount.data('mediaLibrary')) return;

    // No auto-init dentro de modals (picker lo controla)
    if ($mount.closest('.modal').length) return;

    var noAuto = $mount.is('[data-ml-noauto]') || (String($mount.attr('data-ml-auto') || '').trim() === '0');
    if (noAuto) return;

    var tokensSel = resolveTokensForm($mount.attr('data-ml-tokens'));

  
    // mode se infiere: si está en modal => picker, si no => manage
    var modeAttr = $mount.attr('data-ml-mode');
    var mode = modeAttr ? modeAttr : ($mount.closest('.modal').length ? 'picker' : 'manage');

    // syncUrl se infiere por mode, pero respetamos override si existe
    var syncUrlAttr = $mount.attr('data-ml-sync-url');
    var syncUrl = (syncUrlAttr === undefined || syncUrlAttr === null || syncUrlAttr === '')
      ? (mode === 'manage')
      : (String(syncUrlAttr) === '1');

    var kind = $mount.attr('data-ml-kind');
    var saveSource = $mount.attr('data-ml-save-source') || '';
    var tenantID = parseInt($mount.attr('data-ml-tenant-id') || '0', 10) || 0;

    var inst = new MediaLibrary({
      mount: $mount,
      tokens: tokensSel,
      mode: mode,
      kind: kind,
      saveSource: saveSource,
      tenantID: tenantID,
      syncUrlEnabled: syncUrl,
      listenPopState: syncUrl,
      autoload: true
    });

    // upload UI (si existe, lo engancha)
    if (mode === 'manage') {
      inst.attachUpload({
        dropZone: $mount.attr('data-ml-dropzone') || '#drop-zone',
        fileInput: $mount.attr('data-ml-fileinput') || '#file-input',
        uploadButton: $mount.attr('data-ml-uploadbtn') || '#upload_media',
        addPanelButton: $mount.attr('data-ml-addbtn') || '#add_media',
        addPanelWrap: $mount.attr('data-ml-addwrap') || '#add_media_wrap',
        addPanelClose: $mount.attr('data-ml-addclose') || '#close_media_wrap'
      });
    }

    $mount.data('mediaLibrary', inst);
    MediaLibrary.setActive(inst);
  });
};

  // export
  window.MediaLibrary = MediaLibrary;

  // DOM ready => autoinit
  $(function(){
    MediaLibrary.autoInit();
  });

})(jQuery);
