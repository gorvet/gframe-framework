<?php
  $files = $recent['data'];
  $paginate = $recent['meta'];
  $sources = $recent['filters']['sources'] ?? [];
  $showSourceFilter = !empty($sources);
  $pickerFragment = ($fragment ?? 'library') === 'picker';
  $idPrefix = $pickerFragment ? 'mp-' : '';
  $toPublicUrl = static function (string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path) === 1) return $path;
    return site_url . ltrim($path, '/');
  };
?>
<div class="container mb-3">
  <div class="d-flex flex-column flex-md-row align-items-md-center gap-2">

    <!-- Left: filters -->
    <div class="d-flex flex-column flex-sm-row gap-2 flex-grow-1">

      <?php if ($showSourceFilter): ?>
        <!-- Source / Tipo -->
        <div class="input-group">
          <span class="input-group-text">Origen</span>
          <select id="<?= $idPrefix ?>media-source-filter" class="form-select" data-ml-filter="source">
            <option value="all" <?= (($recent['meta']['source'] ?? 'all') === 'all') ? 'selected' : ''; ?>>
              Todos
            </option>

            <?php
              foreach ($sources as $s):
                $val = $s['value'];
                $lbl = $s['label'];
                $tot = (int)($s['total'] ?? 0);
                $sel = (($recent['meta']['source'] ?? 'all') === $val) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($val); ?>" <?php echo $sel; ?>>
                <?= htmlspecialchars($lbl); ?> (<?php echo $tot; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <!-- Tipo -->
      <div class="input-group">
        <span class="input-group-text">Tipo</span>
        <select id="<?= $idPrefix ?>media-kind-filter" class="form-select" data-ml-filter="kind">
          <?php
            $kindValue = (string)($recent['meta']['kind'] ?? 'all');
            $kindOptions = [
              ['value' => 'all', 'label' => 'Todos'],
              ['value' => 'images', 'label' => 'Imagenes'],
              ['value' => 'docs', 'label' => 'Documentos'],
              ['value' => 'audios', 'label' => 'Audios'],
              ['value' => 'videos', 'label' => 'Videos'],
            ];
            foreach ($kindOptions as $kopt):
              $sel = ($kindValue === $kopt['value']) ? 'selected' : '';
          ?>
            <option value="<?= htmlspecialchars($kopt['value']); ?>" <?= $sel; ?>>
              <?= htmlspecialchars($kopt['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Fecha -->
      <div class="input-group">
        <span class="input-group-text">Fecha</span>
        <select id="<?= $idPrefix ?>media-date-filter" class="form-select" data-ml-filter="ym">
          <option value="all" <?= (($recent['meta']['ym'] ?? 'all') === 'all') ? 'selected' : ''; ?>>
            Todas
          </option>

          <?php
            $dates = $recent['filters']['dates'] ?? [];
            foreach ($dates as $d):
              $val = $d['value'];  // YYYY-MM
              $lbl = $d['label'];  // "Febrero 2026"
              $tot = (int)($d['total'] ?? 0);
              $sel = (($recent['meta']['ym'] ?? 'all') === $val) ? 'selected' : '';
          ?>
            <option value="<?= htmlspecialchars($val); ?>" <?= $sel; ?>>
              <?= htmlspecialchars($lbl); ?> (<?= $tot; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>

    <!-- Right: search -->
    <div class="d-flex gap-2">
      <div class="input-group">
        <input
          type="search"
          id="<?= $idPrefix ?>media-search-input"
          data-ml-filter="q"
          class="form-control"
          placeholder="Buscar..."
          value="<?= htmlspecialchars($recent['meta']['q'] ?? ''); ?>"
          autocomplete="off"
        >
        <button id="<?= $idPrefix ?>media-search-btn" data-ml-action="search" class="btn btn-outline-secondary" type="submit" aria-label="Buscar">
          <i class="gicon-search"></i>
        </button>
      </div>
      <button
        id="<?= $idPrefix ?>media-clear-filters-btn"
        class="btn btn-outline-secondary text-nowrap"
        type="button"
        data-ml-action="clear-filters"
      >
        Limpiar filtros
      </button>
    </div>

  </div>
</div>

<?php
  if (!empty($files)) {
    foreach ($files as $f):

      $mediaId = $f['media_id'];
      $mediaURL = $f['media_url'];
      $mediaName = $f['name'] ?? '';
      $mediaMime = $f['mime_type'] ?? '';
      $mediaAlt = $f['alt_text'] ?? '';
      $mediaNameEsc = htmlspecialchars((string)$mediaName, ENT_QUOTES, 'UTF-8');
      $mediaAltEsc = htmlspecialchars((string)$mediaAlt, ENT_QUOTES, 'UTF-8');

      $isImage = (strpos((string)$f['type'], 'images') === 0);
      $isExternal = (preg_match('#^https?://#i', (string)$mediaURL) === 1);

      if ($isImage) {
        $smallURL  = trim((string)($f['thumb_small'] ?? ''));
        $xsmallURL = trim((string)($f['thumb_xsmall'] ?? ''));
        $mediumURL = trim((string)($f['thumb_medium'] ?? ''));

        if ($isExternal) {
          if ($smallURL === '') $smallURL = (string)$mediaURL;
          if ($xsmallURL === '') $xsmallURL = $smallURL;
          if ($mediumURL === '') $mediumURL = $smallURL;
        } else {
          if ($smallURL === '') {
            $smallURL = MediaPathHelper::thumbnailUrl((string)$mediaURL, 'small');
          }
          if ($xsmallURL === '') {
            $xsmallURL = MediaPathHelper::thumbnailUrl((string)$mediaURL, 'xsmall');
          }
          if ($mediumURL === '') {
            $mediumURL = MediaPathHelper::thumbnailUrl((string)$mediaURL, 'medium');
          }

          $smallAbs  = rtrim((string)ABSPATH, '/\\') . '/' . ltrim((string)$smallURL, '/\\');
          $xsmallAbs = rtrim((string)ABSPATH, '/\\') . '/' . ltrim((string)$xsmallURL, '/\\');
          $mediumAbs = rtrim((string)ABSPATH, '/\\') . '/' . ltrim((string)$mediumURL, '/\\');

          if (!is_file($smallAbs)) $smallURL = $mediaURL;
          if (!is_file($xsmallAbs)) $xsmallURL = $smallURL;
          if (!is_file($mediumAbs)) $mediumURL = $smallURL;
        }
      } else {
        $smallURL  = '';
        $xsmallURL = '';
        $mediumURL = '';
      }
?>

  <div class="col-3 col-md-2 col-lg-2 col-sm-2">
    <a
      id="<?= ($pickerFragment ? 'mp-media-' : 'mID_') . (int)$mediaId; ?>"
      class="m-list-card"
      href="#"
      data-media-id="<?= (int)$mediaId; ?>"
      data-media-url="<?= htmlspecialchars($mediaURL, ENT_QUOTES, 'UTF-8'); ?>"
      data-media-thumb-small="<?= $smallURL ? htmlspecialchars($toPublicUrl($smallURL), ENT_QUOTES, 'UTF-8') : ''; ?>"
      data-media-thumb-xsmall="<?= $xsmallURL ? htmlspecialchars($toPublicUrl($xsmallURL), ENT_QUOTES, 'UTF-8') : ''; ?>"
      data-media-thumb-medium="<?= $mediumURL ? htmlspecialchars($toPublicUrl($mediumURL), ENT_QUOTES, 'UTF-8') : ''; ?>"
      data-media-name="<?= $mediaNameEsc; ?>"
      data-media-mime="<?= htmlspecialchars((string)$mediaMime, ENT_QUOTES, 'UTF-8'); ?>"
      data-media-alt="<?= $mediaAltEsc; ?>"
      data-media-type="<?= htmlspecialchars((string)($f['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
      data-media-ext="<?= htmlspecialchars((string)($f['ext'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
    >
      <div class="card mb-3">
        <?php if ($isImage): ?>
          <img class="card-img-top" src="<?= htmlspecialchars($toPublicUrl($smallURL), ENT_QUOTES, 'UTF-8'); ?>" alt="">
        <?php else: ?>
          <?php
            $ext = strtolower(trim((string)($f['ext'] ?? '')));
            if ($ext === '') {
              $pathForExt = (string)(parse_url((string)$mediaURL, PHP_URL_PATH) ?? (string)$mediaURL);
              $ext = strtolower(pathinfo($pathForExt, PATHINFO_EXTENSION));
            }
          ?>

<div   class="card-img-top d-flex  align-items-center justify-content-center bg-light" >
    <div class="text-center position-relative">
        <!-- Icono grande -->
           <img src="<?= site_url. 'public/img/admin/file.png' ?>"
                
             alt="<?= $mediaAltEsc ?>"
             title="<?= $mediaNameEsc ?>"
             class="card-img-top"
              >
        
        <!-- Extensión centrada DENTRO del icono -->
        <div class="badge bg-secondary text-uppercase position-absolute top-50 start-50 translate-middle" style="font-size: .7rem; z-index: 2;"><?php echo htmlspecialchars($ext ?: 'FILE'); ?></div>
        
        <!-- Nombre DEBAJO del icono -->
        <div class="small text-muted p-1 mt-4  position-absolute top-50 start-50 translate-middle" style="max-width: 120px; font-size: .8rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; background: var(--bs-body-bg);"><?php echo htmlspecialchars($mediaName ?: basename((string)$mediaURL)); ?></div>
    </div>
</div>

        <?php endif; ?>
      </div>
    </a>
  </div>

<?php
    endforeach;

    if (isset($paginate['total_pages']) && $paginate['total_pages'] > 1) {
      ob_start();
      PaginationHelper::render($paginate['total_pages'], $paginate['page']);
      $paginationHtml = (string)ob_get_clean();
      if ($pickerFragment) $paginationHtml = str_replace(['id="pagination"', 'id="all_items_pagination"'], ['id="mp-pagination"', 'id="mp-all-items-pagination"'], $paginationHtml);
      echo '<div data-ml-pagination>' . $paginationHtml . '</div>';
    }
}
  else{ echo '<span id="' . ($pickerFragment ? 'mp-no-media' : 'noM') . '">No hay archivos que mostrar.</span>';}
 

 
