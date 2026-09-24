<?php

class SitemapDataProvider extends ORM
{
  protected $table = '';

  public function rows(array $dataset, array $columns): array
  {
    $table = trim((string)($dataset['table'] ?? ''));
    if ($table === '' || !$this->isSafeIdent($table)) return [];

    // Sanitizar columnas
    $columns = array_values(array_unique(array_filter(
      $columns,
      fn($c) => $this->isSafeIdent((string)$c)
    )));
    if (empty($columns)) return [];

    // Límite interno (con clamp por seguridad)
    $limit = (int)($dataset['limit'] ?? 50000);
    if ($limit < 1) $limit = 1;
    if ($limit > 50000) $limit = 50000;

    // Setear tabla y armar query
    // ✅ Desactiva strictCompare para evitar CAST(...) en WHERE
    $this->table = $table;

    $q = $this->useStrictComparison(false)
              ->reset()
              ->select(...$columns);

    // Conditions
    $conds = $dataset['conditions'] ?? [];
    if (is_array($conds)) {
      $this->applyConditions($q, $conds);
    }

    $q->limit($limit);

    $rows = $q->get();
    if (!is_array($rows) || empty($rows)) return [];

    return $this->normalizeRows($rows, $columns);
  }

  private function normalizeRows(array $rows, array $columns): array
  {
    $imageColumns = $this->detectImageColumns($columns);
    $mediaIds = [];

    foreach ($rows as $row) {
      $row = is_object($row) ? (array)$row : (array)$row;

      foreach ($imageColumns as $column) {
        $value = $row[$column] ?? null;
        if (is_numeric($value) && (int)$value > 0) {
          $mediaIds[] = (int)$value;
        }
      }
    }

    $mediaUrls = $this->fetchMediaUrlsByIds($mediaIds);
    $normalized = [];

    foreach ($rows as $row) {
      $row = is_object($row) ? (array)$row : (array)$row;

      if (!empty($row['media_url']) && is_string($row['media_url'])) {
        $row['media_url'] = $this->absoluteUrl($row['media_url']);
      }

      foreach ($imageColumns as $column) {
        $value = $row[$column] ?? null;

        if (is_numeric($value)) {
          $mediaId = (int)$value;
          if ($mediaId > 0 && !empty($mediaUrls[$mediaId])) {
            $row[$column] = $mediaUrls[$mediaId];
          }
          continue;
        }

        if (is_string($value) && $value !== '') {
          $row[$column] = $this->absoluteUrl($value);
        }
      }

      $normalized[] = $row;
    }

    return $normalized;
  }

  private function detectImageColumns(array $columns): array
  {
    $matches = [];

    foreach ($columns as $column) {
      $column = (string)$column;
      if ($column === '') continue;

      if (preg_match('/(^|_)(image|cover_image|thumbnail|thumb|poster|banner)$/i', $column) === 1) {
        $matches[] = $column;
      }
    }

    return array_values(array_unique($matches));
  }

  private function fetchMediaUrlsByIds(array $mediaIds): array
  {
    $mediaIds = array_values(array_unique(array_filter(array_map('intval', $mediaIds), fn($id) => $id > 0)));
    if (empty($mediaIds)) return [];

    $mediaQuery = new self();
    $mediaQuery->table = 'medias';

    $rows = $mediaQuery->useStrictComparison(false)
      ->reset()
      ->select('media_id', 'media_url')
      ->whereIn('media_id', $mediaIds)
      ->get();

    if (!is_array($rows) || empty($rows)) return [];

    $resolved = [];
    foreach ($rows as $row) {
      $row = is_object($row) ? (array)$row : (array)$row;
      $mediaId = (int)($row['media_id'] ?? 0);
      $mediaUrl = trim((string)($row['media_url'] ?? ''));
      if ($mediaId <= 0 || $mediaUrl === '') continue;

      $resolved[$mediaId] = $this->absoluteUrl($mediaUrl);
    }

    return $resolved;
  }

  private function absoluteUrl(string $path): string
  {
    $path = trim($path);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
      return $path;
    }

    return rtrim((string)site_url, '/') . '/' . ltrim($path, '/\\');
  }

  private function applyConditions($q, array $conds): void
  {
    foreach ($conds as $key => $val) {

      // Permite formato: [ ['id','!=',1], ['is_public','=',1] ]
      if (is_int($key) && is_array($val) && count($val) >= 3) {
        [$col, $op, $v] = $val;
        $col = (string)$col;
        $op  = strtoupper(trim((string)$op));
        if (!$this->isSafeIdent($col)) continue;

        $this->applyOneCondition($q, $col, $op, $v);
        continue;
      }

      // Formato simple: 'is_public' => 1
      // Formato con operador: 'id !=' => 1, 'published_at >=' => '2026-01-01'
      $key = trim((string)$key);
      if ($key === '') continue;

      $parts = preg_split('/\s+/', $key, 2);
      $col = $parts[0] ?? '';
      $op  = strtoupper($parts[1] ?? '=');

      if (!$this->isSafeIdent($col)) continue;

      $this->applyOneCondition($q, $col, $op, $val);
    }
  }

  private function applyOneCondition($q, string $col, string $op, $val): void
  {
    // IN (...)
    if (is_array($val)) {
      $q->whereIn($col, $val);
      return;
    }

    // NULL -> IS NULL / IS NOT NULL
    if ($val === null) {
      if ($op === '!=' || $op === '<>' || $op === 'IS NOT') {
        $q->whereNotNull($col);
      } else {
        $q->whereNull($col);
      }
      return;
    }

    // Operadores permitidos (mínimo)
    $allowed = ['=','!=','<>','>','>=','<','<=','LIKE'];
    if (!in_array($op, $allowed, true)) $op = '=';

    if ($op === 'LIKE') {
      // si quieres escapar % _ de forma segura, usa whereLike del ORM
      $q->whereLike($col, (string)$val);
      return;
    }

    $q->where($col, $op, $val);
  }

  private function isSafeIdent(string $s): bool
  {
    return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $s);
  }
}
