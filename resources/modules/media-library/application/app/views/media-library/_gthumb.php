  <?php 

 $img = is_array($ctx) ? $ctx : (array)$ctx;

   $id = (int)($img['media_id'] ?? 0);
    $toPublicUrl = static function (string $path): string {
      $path = trim($path);
      if ($path === '') return '';
      if (preg_match('#^https?://#i', $path) === 1) return $path;
      return site_url . ltrim($path, '/');
    };

    $mediaUrl = (string)($img['media_url'] ?? '');

    // Las URL de miniaturas ya fueron resueltas por el backend.
    $smallURL = trim((string)($img['thumb_small'] ?? '')) ?: $mediaUrl;

    $alt  = $img['alt_text'] ?? ($img['name'] ?? '');
    $altE = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $ext  = htmlspecialchars($img['ext'] ?? 'FILE', ENT_QUOTES, 'UTF-8');;
    $type  = $img['type']??'' ;
    $name  = htmlspecialchars($img['name']??'', ENT_QUOTES, 'UTF-8');
     ?>

  <div class="media-thumb" data-ml-id="<?= $id ?>">

    <?php if ($type=='images'): ?>
        <img src="<?= htmlspecialchars($toPublicUrl($smallURL), ENT_QUOTES, 'UTF-8') ?>"
             alt="<?= $altE ?>"
             title="<?= $name ?>"
             class="img-thumbnail w-100"
              data-ml-media-pick>

    <?php else: ?>
      

        <div   class="card-img-top d-flex  align-items-center justify-content-center " >
            <div class="text-center position-relative  bg-ligth">
                <!-- Icono grande -->
                 <img src="<?= site_url. 'public/img/admin/file.png' ?>"
             alt="<?= $altE ?>"
             title="<?= $name ?>"
             class="img-thumbnail w-100"
              data-ml-media-pick>
               

                <!-- Extensión centrada DENTRO del icono -->
                <div id="" class="badge bg-secondary text-uppercase position-absolute top-50 start-50 translate-middle" style="font-size: .7rem; z-index: 2;"><?=  $ext; ?></div>
            </div>
        </div>

    <?php endif; ?>
  <?php if ($allowRemoveOne ?? true): ?><button type="button" class="btn btn-sm btn-link text-danger p-0 mt-1" data-ml-media-remove title="Quitar">Quitar</button><?php endif; ?>
    

</div>
