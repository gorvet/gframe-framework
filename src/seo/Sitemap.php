<?php

class Sitemap {

  protected $sitemapDataProvider;

  public function __construct() {
    $this->sitemapDataProvider = new SitemapDataProvider();
  }

  public function index($routeParams = []) {
    $routesByMethod = RouteBuilder::all();
    $items = $this->collectPublicGetRoutes($routesByMethod);

    header('Content-Type: application/xml; charset=UTF-8');
    echo $this->renderUrlset($items);
    exit;
  }

  private function collectPublicGetRoutes(array $routesByMethod): array {
    $items = [];
    $GET = $routesByMethod['GET'] ?? [];

    foreach ($GET as $uri => $r) {

      // 1) Solo rutas web públicas
      if (($r['type'] ?? 'web') !== 'web') continue;

      // 1.1) Excluir por contrato explícito de ruta
      if (($r['context']['sitemap']['include'] ?? true) === false) continue;

      // 2) Excluir rutas con auth/permiso
      $mw = $r['middleware'] ?? [];
      if (!empty($r['permission'])) continue;
      if (in_array('auth', $mw, true)) continue;
      if (array_filter($mw, fn($m) => strpos($m, 'auth') === 0)) continue;

      // 3) Excluir zonas privadas y el propio sitemap
      $path = trim($uri, '/');
      if ($path === 'sitemap.xml') continue;
      if ($path !== '' && preg_match('#^(admin|dashboard|api|ajax|webhook|auth|login|logout)(/|$)#i', $path)) continue;

      // ====== DINÁMICAS ======
      if (strpos($uri, '{') !== false) {
        $sm = $this->loadSitemapMeta($r); // si no hay contrato, devuelve []
        $dynItems = $this->expandDynamicRoute($uri, $r, $sm);
        if (!empty($dynItems)) $items = array_merge($items, $dynItems);
        continue; // nunca incluir plantilla /poema/{id}
      }

      // ====== ESTÁTICAS ======
      $sm = $this->loadSitemapMeta($r); // opt-in para overrides

      $loc = rtrim(site_url, '/') . ($path ? '/'.$path : '');
      $loc = preg_replace('#(?<!:)//+#', '/', $loc);

      // lastmod automático por mtime (vista + meta)
      $lastmodAuto = null;
      $relative = RouteBuilder::relativePathFromController($r['controller'] ?? '');
      $view     = $r['view'] ?? RouteBuilder::inferViewName($r['controller'] ?? '', $r['action'] ?? '');

      $mtimes = [];
      if ($relative && $view) {
        $viewPath = realpath(ABSPATH . "app/views/{$relative}/{$view}.php");
        $metaPath = realpath(ABSPATH . "app/views/{$relative}/{$view}.meta.php");
        if ($viewPath && file_exists($viewPath)) $mtimes[] = filemtime($viewPath);
        if ($metaPath && file_exists($metaPath)) $mtimes[] = filemtime($metaPath);
      }
      if ($mtimes) $lastmodAuto = date('c', max($mtimes));

      // Orden de precedencia (lo lógico):
      // auto < meta < context
      $lastmod = $lastmodAuto;
      if (!empty($sm['lastmod'])) $lastmod = $sm['lastmod'];
      if (!empty($r['context']['lastmod'])) $lastmod = $r['context']['lastmod'];

      $changefreq = $sm['changefreq'] ?? null;
      if (isset($r['context']['changefreq'])) $changefreq = $r['context']['changefreq'];

      $priority = $sm['priority'] ?? null;
      if (isset($r['context']['priority'])) $priority = $r['context']['priority'];
      $priority = $priority ?? $this->calcPriority($path);

      $items[] = [
        'loc'        => $loc,
        'lastmod'    => $lastmod,
        'changefreq' => $changefreq,
        'priority'   => $priority,
        'alternates' => [],
        'images'     => (!empty($sm['images']) && is_array($sm['images'])) ? $sm['images'] : [],

      ];
    }

    // De-duplicar por loc
    $seen = []; $out = [];
    foreach ($items as $it) {
      if (!isset($seen[$it['loc']])) { $seen[$it['loc']] = true; $out[] = $it; }
    }
    return $out;
  }

  private function expandDynamicRoute(string $uriTemplate, array $r, array $sm): array {
    $dyn = $sm['dynamic'] ?? null;
    if (empty($dyn) || !is_array($dyn)) return [];

    $paramMap = $dyn['params'] ?? [];
    $dataset  = $dyn['dataset'] ?? [];
    $columns  = $dyn['columns'] ?? [];

    if (empty($paramMap) || empty($dataset['table']) || empty($columns)) return [];

    $lastmodKey = $dyn['lastmod'] ?? null;
    $imageKey   = $dyn['image'] ?? null;
    $imageBase  = $dyn['image_base'] ?? '';

    // Seguridad mínima: si falta algo en columns, lo añadimos.
    foreach (array_values($paramMap) as $c) if (!in_array($c, $columns, true)) $columns[] = $c;
    if ($lastmodKey && !in_array($lastmodKey, $columns, true)) $columns[] = $lastmodKey;
    if ($imageKey   && !in_array($imageKey, $columns, true))   $columns[] = $imageKey;

    $rows = $this->sitemapDataProvider->rows($dataset, $columns);
    if (empty($rows)) return [];

    // Precedencia: calcPriority < meta < context
    $priority = $sm['priority'] ?? null;
    if (isset($r['context']['priority'])) $priority = $r['context']['priority'];
    $priority = $priority ?? $this->calcPriority(trim($uriTemplate,'/'));

    $changefreq = $sm['changefreq'] ?? null;
    if (isset($r['context']['changefreq'])) $changefreq = $r['context']['changefreq'];

    $items = [];

    foreach ($rows as $row) {
      if (is_object($row)) $row = (array)$row;

      // Sustituir {param} por el valor del row
      $built = $uriTemplate;
      foreach ($paramMap as $param => $col) {
        if (!isset($row[$col])) continue 2;
        $built = str_replace('{'.$param.'}', rawurlencode((string)$row[$col]), $built);
      }
      if (strpos($built, '{') !== false) continue;
      $path = trim($built, '/');
      $loc = rtrim(site_url, '/') . ($path ? '/'.$path : '');
      $loc = preg_replace('#(?<!:)//+#', '/', $loc);

      $lastmod = null;
      if ($lastmodKey && !empty($row[$lastmodKey])) {
        $lastmod = date('c', strtotime((string)$row[$lastmodKey]));
      }

      $images = [];
      if ($imageKey && !empty($row[$imageKey])) {
        $img = (string)$row[$imageKey];
        if (preg_match('#^https?://#i', $img)) $images[] = $img;
        else if ($imageBase) $images[] = rtrim($imageBase,'/') . '/' . ltrim($img,'/');
      }

      $items[] = [
        'loc'        => $loc,
        'lastmod'    => $lastmod,
        'changefreq' => $changefreq,
        'priority'   => $priority,
        'alternates' => [],
        'images'     => $images,
      ];
    }

    return $items;
  }

  private function loadSitemapMeta(array $r): array {
    $relative = RouteBuilder::relativePathFromController($r['controller'] ?? '');
    $view     = $r['view'] ?? RouteBuilder::inferViewName($r['controller'] ?? '', $r['action'] ?? '');
    if (!$relative || !$view) return [];

    $metaPath = realpath(ABSPATH . "app/views/{$relative}/{$view}.meta.php");
    if (!$metaPath || !file_exists($metaPath)) return [];

    // ✅ Blindaje: si la meta no tiene contrato o revienta, se ignora
    try {
      $metaData = require $metaPath;
    } catch (\Throwable $e) {
      return [];
    }

    if (is_array($metaData) && !empty($metaData['sitemap']) && is_array($metaData['sitemap'])) {
      return $metaData['sitemap'];
    }
    return [];
  }

 private function renderUrlset(array $items): string {
  $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"; $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '; $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '; $xml .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 '; $xml .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" '; $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" '; $xml .= 'xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
  foreach ($items as $r) {
    $xml .= "  <url>\n";
    $xml .= '    <loc>'.htmlspecialchars($r['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8')."</loc>\n";

    if (!empty($r['lastmod'])) {
      $xml .= '    <lastmod>'.htmlspecialchars($r['lastmod'], ENT_QUOTES | ENT_XML1, 'UTF-8')."</lastmod>\n";
    }
    if (!empty($r['changefreq'])) {
      $xml .= '    <changefreq>'.htmlspecialchars($r['changefreq'], ENT_QUOTES | ENT_XML1, 'UTF-8')."</changefreq>\n";
    }
    if (!empty($r['priority'])) {
      $xml .= '    <priority>'.htmlspecialchars($r['priority'], ENT_QUOTES | ENT_XML1, 'UTF-8')."</priority>\n";
    }

    if (!empty($r['images'])) {
      foreach ($r['images'] as $img) {
        $img = trim((string)$img);
        if ($img === '') continue;

        $xml .= "    <image:image>\n";
        $xml .= '      <image:loc>'.htmlspecialchars($img, ENT_QUOTES | ENT_XML1, 'UTF-8')."</image:loc>\n";
        $xml .= "    </image:image>\n";
      }
    }

    $xml .= "  </url>\n";
  }

  $xml .= "</urlset>";
  return $xml;
}


  private function calcPriority(string $path): string {
    if ($path === '' || $path === '/') return '1.0';
    $depth = substr_count(trim($path, '/'), '/');
    return match (true) {
      $depth === 0 => '0.8',
      $depth === 1 => '0.6',
      default      => '0.5',
    };
  }
}
