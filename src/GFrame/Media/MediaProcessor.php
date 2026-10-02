<?php
namespace GFrame\Media;

// app/services/media/MediaProcessor.php
// Processing and detection (MIME/image/variants) for Media Manager.

class MediaProcessor {

  // Security: blocked extensions.
  private const BLOCKED_EXTS = ['php','phtml','phar','js','html','htm','svg','sh','bat','exe'];

  // Single source of truth by kind.
  private const ALLOWED_BY_KIND = [
    'images' => [
      'exts'  => ['jpg','jpeg','png','webp','gif'],
      'mimes' => ['image/jpeg','image/png','image/webp','image/gif'],
    ],

    'docs' => [
      'exts'  => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','json'],
      'mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/json',
      ],
    ],

    'audios' => [
      'exts'  => ['mp3','wav','ogg','opus','m4a','aac','flac'],
      'mimes' => [
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'audio/opus',
        'audio/mp4',
        'audio/aac',
        'audio/flac',
      ],
    ],

    'videos' => [
      'exts'  => ['mp4','webm','mov','mkv','avi'],
      'mimes' => [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-matroska',
        'video/x-msvideo',
      ],
    ],
  ];

  private const MAX_IMAGE_PIXELS = 80000000; // 80 MP

  protected function configuration(): array {
    return require dirname(__DIR__, 3) . '/resources/modules/media-library/config/media.php';
  }

  public function getMaxUploadBytes(): int {
    return max(1, (int)($this->configuration()['max_upload_bytes'] ?? 26214400));
  }

  public function getVariantDefinitions(): array {
    $variants = (array)($this->configuration()['variants'] ?? []);
    foreach ($variants as $key => $variant) {
      if (!preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', (string)$key)
          || !is_array($variant) || !in_array($variant['mode'] ?? '', ['crop', 'fit'], true)
          || (int)($variant['w'] ?? 0) < 1 || (int)($variant['h'] ?? 0) < 1
          || (int)$variant['w'] > 16384 || (int)$variant['h'] > 16384
          || (int)$variant['w'] * (int)$variant['h'] > self::MAX_IMAGE_PIXELS) {
        throw new \InvalidArgumentException('La configuración de tamaños multimedia no es válida.');
      }
    }
    return $variants;
  }

  private string $watermarkPath = 'public/img/shop/watermark.png';

  public function getAllowedByKind(): array {
    return array_keys($this->getAllowedByKindMap());
  }

  public function getAllowedByKindMap(): array {
    $map = [];
    foreach ((array)($this->configuration()['allowed_extensions'] ?? []) as $kind => $extensions) {
      if (!isset(self::ALLOWED_BY_KIND[$kind])) continue;
      $allowed = self::ALLOWED_BY_KIND[$kind];
      $extensions = array_map(static fn($ext) => strtolower(trim((string)$ext)), (array)$extensions);
      $allowed['exts'] = array_values(array_intersect($allowed['exts'], $extensions));
      if ($allowed['exts'] !== []) $map[$kind] = $allowed;
    }
    return $map;
  }

  public function getBlockedExts(): array {
    return array_values(self::BLOCKED_EXTS);
  }

  // Backward compatibility with previous typo.
  public function getBlokedExts(): array {
    return $this->getBlockedExts();
  }

  public function getVariantKeys(): array {
    return array_keys($this->getVariantDefinitions());
  }

  public function classify(?string $mimeType, ?string $nameOrUrl = null): array {
    $mime = strtolower(trim((string)$mimeType));
    $ext = '';

    if (is_string($nameOrUrl) && $nameOrUrl !== '') {
      $path = (string)(parse_url($nameOrUrl, PHP_URL_PATH) ?? $nameOrUrl);
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      if ($ext === 'jpeg') $ext = 'jpg';
      $ext = preg_replace('/[^a-z0-9]+/', '', $ext);
    }

    $type = 'unknown';

    if ($mime !== '') {
      if (str_starts_with($mime, 'image/')) $type = 'images';
      elseif (str_starts_with($mime, 'audio/')) $type = 'audios';
      elseif (str_starts_with($mime, 'video/')) $type = 'videos';
      else $type = 'docs';
    } elseif ($ext !== '') {
      if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) $type = 'images';
      elseif (in_array($ext, ['mp3','wav','ogg','opus','m4a','aac','flac'], true)) $type = 'audios';
      elseif (in_array($ext, ['mp4','webm','mov','mkv','avi'], true)) $type = 'videos';
      else $type = 'docs';
    }

    return ['type' => $type, 'ext' => $ext, 'mime' => $mime];
  }

  public function mimeToTypeExt(?string $mimeType): array {
    $mime = strtolower(trim((string)$mimeType));
    if ($mime === '') return ['type' => 'unknown', 'ext' => '', 'mime' => ''];

    if (str_starts_with($mime, 'image/')) {
      $ext = $this->imageExtFromMime($mime) ?? '';
      return ['type' => 'images', 'ext' => $ext, 'mime' => $mime];
    }
    if (str_starts_with($mime, 'audio/')) return ['type' => 'audios', 'ext' => '', 'mime' => $mime];
    if (str_starts_with($mime, 'video/')) return ['type' => 'videos', 'ext' => '', 'mime' => $mime];

    $map = [
      'application/pdf' => 'pdf',
      'application/json' => 'json',
      'text/plain' => 'txt',
      'text/csv' => 'csv',
      'application/msword' => 'doc',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
      'application/vnd.ms-excel' => 'xls',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
      'application/vnd.ms-powerpoint' => 'ppt',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];

    return [
      'type' => 'docs',
      'ext'  => $map[$mime] ?? '',
      'mime' => $mime,
    ];
  }

  public function detectMime(string $filePath): string {
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo) {
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        if (is_string($mime) && $mime !== '') return $mime;
      }
    }
    if (function_exists('mime_content_type')) {
      $mime = mime_content_type($filePath);
      if (is_string($mime) && $mime !== '') return $mime;
    }
    return '';
  }

  public function isLikelyImage(string $mimeType, string $ext): bool {
    $mime = strtolower(trim($mimeType));
    $ext  = strtolower(trim($ext));
    if ($ext === 'jpeg') $ext = 'jpg';
    if (str_starts_with($mime, 'image/')) return true;
    return ($mime === 'application/octet-stream' && in_array($ext, ['jpg','png','webp','gif'], true));
  }

  public function validateImageUpload(string $tmpPath, array $allowedMimes): array {
    $imgInfo = @getimagesize($tmpPath);
    if (!$imgInfo) {
      return ['ok' => false, 'code' => 'not_image', 'message' => 'No es una imagen valida'];
    }

    $imgMime = strtolower(trim((string)($imgInfo['mime'] ?? '')));
    if ($imgMime === '') $imgMime = 'application/octet-stream';

    if (!in_array($imgMime, $allowedMimes, true)) {
      return ['ok' => false, 'code' => 'mime_not_allowed', 'message' => 'Mimetype no permitido'];
    }

    $w = (int)($imgInfo[0] ?? 0);
    $h = (int)($imgInfo[1] ?? 0);
    if ($w < 1 || $h < 1 || ($w * $h) > self::MAX_IMAGE_PIXELS) {
      return ['ok' => false, 'code' => 'image_too_large', 'message' => 'Imagen demasiado grande'];
    }

    $ext = $this->imageExtFromMime($imgMime);
    if (!$ext) {
      return ['ok' => false, 'code' => 'mime_not_allowed', 'message' => 'Mimetype no permitido'];
    }

    if ($ext === 'webp' && !$this->gdSupportsWebp()) {
      return ['ok' => false, 'code' => 'webp_not_supported', 'message' => 'WebP no soportado en el servidor'];
    }

    return ['ok' => true, 'mime' => $imgMime, 'w' => $w, 'h' => $h, 'ext' => $ext];
  }

  public function validateNonImageMime(string $tmpPath, string $kind, string $mimeType, string $ext, array $allowedMimes): array {
    $mime = strtolower(trim($mimeType));
    if ($mime === '') $mime = 'application/octet-stream';

    $ext = strtolower(trim($ext));
    if ($ext === 'jpeg') $ext = 'jpg';

    $mimeOk = in_array($mime, $allowedMimes, true);

    if (!$mimeOk && $mime === 'application/zip' && in_array($ext, ['docx','xlsx','pptx'], true)) {
      $mimeOk = $this->looksLikeZip($tmpPath);
    }

    if (!$mimeOk && $mime === 'application/octet-stream') {
      if ($kind === 'docs' || $kind === 'all') {
        $mimeOk = $this->sniffByExt($tmpPath, $ext);

        if ($mimeOk && $ext === 'pdf')  $mime = 'application/pdf';
        if ($mimeOk && $ext === 'json') $mime = 'application/json';
        if ($mimeOk && $ext === 'csv')  $mime = 'text/csv';
        if ($mimeOk && $ext === 'txt')  $mime = 'text/plain';
      }
    }

    if (!$mimeOk) {
      return ['ok' => false, 'code' => 'mime_not_allowed', 'message' => 'Mimetype no permitido', 'mime' => $mime];
    }

    return ['ok' => true, 'mime' => $mime];
  }

  public function generateVariants(
    string $folderAbs,
    string $folderRel,
    string $absOriginal,
    string $fileName,
    string $ext,
    string $source
  ): array {

    $sizes = [];
    $stem  = pathinfo($fileName, PATHINFO_FILENAME);

    $originalInfo = @getimagesize($absOriginal);
    foreach ($this->getVariantDefinitions() as $key => $cfg) {
      if (!$originalInfo || ((int)$originalInfo[0] <= (int)$cfg['w'] && (int)$originalInfo[1] <= (int)$cfg['h'])) continue;
      if ($cfg['mode'] === 'crop' && ((int)$originalInfo[0] < (int)$cfg['w'] || (int)$originalInfo[1] < (int)$cfg['h'])) continue;
      $variantFile = "{$stem}-{$key}.{$ext}";
      $variantAbs  = $folderAbs . '/' . $variantFile;

      $ok = false;
      if (($cfg['mode'] ?? 'crop') === 'optimize') {
        $ok = $this->optimizeOriginal($absOriginal, $variantAbs, $ext);
      } elseif (($cfg['mode'] ?? 'crop') === 'fit') {
        $ok = $this->resizeFit($absOriginal, $variantAbs, (int)$cfg['w'], (int)$cfg['h'], $ext);
      } else {
        $ok = $this->ejResizeAndCropImage($absOriginal, $variantAbs, (int)$cfg['w'], (int)$cfg['h'], $ext);
      }

      if ($ok) {
        if ($key === 'optimized') {
          $originalBytes = (int)@filesize($absOriginal);
          $variantBytes = (int)@filesize($variantAbs);
          if ($originalBytes > 0 && $variantBytes >= $originalBytes) {
            @unlink($variantAbs);
            continue;
          }
        }
        $sizes[$key] = $folderRel . '/' . $variantFile;
      }
    }

    // Compatibilidad con el frontend existente sin crear archivos extra.
    if (isset($sizes['small']) && !isset($sizes['xsmall'])) {
      $sizes['xsmall'] = $sizes['small'];
    }
    if (isset($sizes['small']) && !isset($sizes['medium'])) {
      $sizes['medium'] = $sizes['small'];
    }
    if (isset($sizes['optimized']) && !isset($sizes['preview'])) {
      $sizes['preview'] = $sizes['optimized'];
    }
    if (isset($sizes['small']) && !isset($sizes['preview'])) {
      $sizes['preview'] = $sizes['small'];
    }
    if (isset($sizes['optimized']) && !isset($sizes['large'])) {
      $sizes['large'] = $sizes['optimized'];
    }

    return $sizes;
  }

  public function optimizeOriginal(string $srcPath, string $dstPath, string $ext): bool {
    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width < 1 || $height < 1) return false;

    $originalBytes = (int)@filesize($srcPath);
    $targetBytes = $originalBytes > 0 ? (int)floor($originalBytes * 0.80) : 0;
    $maxLongSide = 1600;
    $scales = [1.0, 0.92, 0.85, 0.78, 0.72, 0.66, 0.60];
    $qualities = match (strtolower($ext)) {
      'jpg', 'jpeg' => [78, 72, 66, 60],
      'webp' => [76, 70, 64, 58],
      'png' => [9],
      'gif' => [null],
      default => [null],
    };

    $bestPath = '';
    $bestBytes = 0;
    $attempt = 0;

    foreach ($scales as $scale) {
      $scaledWidth = max(1, (int)floor($width * $scale));
      $scaledHeight = max(1, (int)floor($height * $scale));
      $longSide = max($scaledWidth, $scaledHeight);

      if ($longSide > $maxLongSide) {
        $ratio = $maxLongSide / $longSide;
        $scaledWidth = max(1, (int)floor($scaledWidth * $ratio));
        $scaledHeight = max(1, (int)floor($scaledHeight * $ratio));
      }

      foreach ($qualities as $quality) {
        $attempt++;
        $candidatePath = $dstPath . '.tmp-' . $attempt;
        if (!$this->resizeExact($srcPath, $candidatePath, $scaledWidth, $scaledHeight, $ext, $quality)) {
          @unlink($candidatePath);
          continue;
        }

        $candidateBytes = (int)@filesize($candidatePath);
        if ($candidateBytes <= 0) {
          @unlink($candidatePath);
          continue;
        }

        if ($bestPath === '' || $candidateBytes < $bestBytes) {
          if ($bestPath !== '' && is_file($bestPath)) {
            @unlink($bestPath);
          }
          $bestPath = $candidatePath;
          $bestBytes = $candidateBytes;
        } else {
          @unlink($candidatePath);
        }

        if ($targetBytes > 0 && $bestBytes <= $targetBytes) {
          break 2;
        }
      }
    }

    if ($bestPath === '' || !is_file($bestPath)) return false;
    if (is_file($dstPath)) @unlink($dstPath);

    return @rename($bestPath, $dstPath);
  }

  public function resizeFit(string $srcPath, string $dstPath, int $maxW, int $maxH, string $ext): bool {
    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $w = (int)$info[0];
    $h = (int)$info[1];

    $scale = min(1, min($maxW / max(1, $w), $maxH / max(1, $h)));
    $newW = (int)round($w * $scale);
    $newH = (int)round($h * $scale);

    return $this->resizeExact($srcPath, $dstPath, $newW, $newH, $ext);
  }

  public function ejResizeAndCropImage(string $srcPath, string $dstPath, int $targetW, int $targetH, string $ext): bool {
    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $srcW = (int)$info[0];
    $srcH = (int)$info[1];

    $src = $this->imageCreateFrom($srcPath, $ext);
    if (!$src) return false;

    $srcRatio = $srcW / max(1, $srcH);
    $dstRatio = $targetW / max(1, $targetH);

    if ($srcRatio > $dstRatio) {
      $newH = $targetH;
      $newW = (int)round($targetH * $srcRatio);
    } else {
      $newW = $targetW;
      $newH = (int)round($targetW / $srcRatio);
    }

    $tmp = imagecreatetruecolor($newW, $newH);
    $this->enableAlpha($tmp, $ext);

    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

    $x = (int)floor(($newW - $targetW) / 2);
    $y = (int)floor(($newH - $targetH) / 2);

    $dst = imagecreatetruecolor($targetW, $targetH);
    $this->enableAlpha($dst, $ext);

    imagecopy($dst, $tmp, 0, 0, $x, $y, $targetW, $targetH);

    $ok = $this->imageSaveTo($dst, $dstPath, $ext);

    imagedestroy($src);
    imagedestroy($tmp);
    imagedestroy($dst);
    return $ok;
  }

  public function applyWatermarkPng(string $imagePath, string $watermarkPath): void {
    $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $img = $this->imageCreateFrom($imagePath, $ext);
    if (!$img) return;

    $wm = @imagecreatefrompng($watermarkPath);
    if (!$wm) { imagedestroy($img); return; }

    imagesavealpha($wm, true);

    $imgW = imagesx($img);
    $imgH = imagesy($img);
    $wmW  = imagesx($wm);
    $wmH  = imagesy($wm);

    $x = max(0, $imgW - $wmW - 10);
    $y = max(0, $imgH - $wmH - 10);

    imagecopy($img, $wm, $x, $y, 0, 0, $wmW, $wmH);
    $this->imageSaveTo($img, $imagePath, $ext);

    imagedestroy($wm);
    imagedestroy($img);
  }

  public function imageExtFromMime(string $mime): ?string {
    return match ($mime) {
      'image/jpeg' => 'jpg',
      'image/png'  => 'png',
      'image/webp' => 'webp',
      'image/gif'  => 'gif',
      default      => null,
    };
  }

  public function gdSupportsWebp(): bool {
    if (!function_exists('imagewebp')) return false;
    $info = function_exists('gd_info') ? gd_info() : [];
    return !empty($info['WebP Support']);
  }

  public function imageCreateFrom(string $path, string $ext) {
    $ext = strtolower($ext);
    return match ($ext) {
      'jpg','jpeg' => @imagecreatefromjpeg($path),
      'png'        => @imagecreatefrompng($path),
      'gif'        => @imagecreatefromgif($path),
      'webp'       => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
      default      => null,
    };
  }

  public function imageSaveTo($im, string $path, string $ext): bool {
    return $this->imageSaveToWithQuality($im, $path, $ext, null);
  }

  public function imageSaveToWithQuality($im, string $path, string $ext, ?int $quality = null): bool {
    $ext = strtolower($ext);
    return match ($ext) {
      'jpg','jpeg' => @imagejpeg($im, $path, $quality ?? 85),
      'png'        => @imagepng($im, $path, $quality ?? 6),
      'gif'        => @imagegif($im, $path),
      'webp'       => function_exists('imagewebp') ? @imagewebp($im, $path, $quality ?? 80) : false,
      default      => false,
    };
  }

  public function enableAlpha($im, string $ext): void {
    $ext = strtolower($ext);
    if (in_array($ext, ['png','gif','webp'], true)) {
      imagealphablending($im, false);
      imagesavealpha($im, true);
      $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
      imagefilledrectangle($im, 0, 0, imagesx($im), imagesy($im), $transparent);
    }
  }

  public function sniffByExt(string $path, string $ext): bool {
    $ext = strtolower(trim($ext));
    if ($ext === 'jpeg') $ext = 'jpg';

    if ($ext === 'pdf') return $this->looksLikePdf($path);
    if (in_array($ext, ['docx','xlsx','pptx'], true)) return $this->looksLikeZip($path);

    if ($ext === 'json') {
      $s = $this->readHead($path, 262144);
      if ($s === '') return false;
      json_decode($s, true);
      return json_last_error() === JSON_ERROR_NONE;
    }

    if ($ext === 'csv' || $ext === 'txt') {
      $s = $this->readHead($path, 262144);
      if ($s === '') return false;
      return $this->mostlyText($s);
    }

    if (in_array($ext, ['doc','xls','ppt'], true)) return true;

    return false;
  }

  public function looksLikePdf(string $path): bool {
    return $this->readHead($path, 8) === '%PDF-1.';
  }

  public function looksLikeZip(string $path): bool {
    $h = $this->readHead($path, 4);
    return $h === "PK\x03\x04" || $h === "PK\x05\x06" || $h === "PK\x07\x08";
  }

  public function readHead(string $path, int $bytes = 1024): string {
    $fh = @fopen($path, 'rb');
    if (!$fh) return '';
    $data = (string)@fread($fh, $bytes);
    @fclose($fh);
    return $data;
  }

  public function mostlyText(string $s): bool {
    $len = strlen($s);
    if ($len === 0) return false;

    $binary = 0;
    for ($i = 0; $i < $len; $i++) {
      $c = ord($s[$i]);
      if ($c === 9 || $c === 10 || $c === 13) continue;
      if ($c >= 32 && $c <= 126) continue;
      if ($c >= 127) continue;
      $binary++;
    }

    return ($binary / $len) < 0.05;
  }

  private function resizeExact(string $srcPath, string $dstPath, int $newWidth, int $newHeight, string $ext, ?int $quality = null): bool {
    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $srcWidth = (int)($info[0] ?? 0);
    $srcHeight = (int)($info[1] ?? 0);
    if ($srcWidth < 1 || $srcHeight < 1 || $newWidth < 1 || $newHeight < 1) return false;

    $src = $this->imageCreateFrom($srcPath, $ext);
    if (!$src) return false;

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    $this->enableAlpha($dst, $ext);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

    $ok = $this->imageSaveToWithQuality($dst, $dstPath, $ext, $quality);

    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
  }
}
