# ORM Query Patterns

## Base Table Queries

When the query targets the model's own table, use the model directly:

```php
$row = $this->reset()
  ->where('project_id', '=', $projectID)
  ->limit(1)
  ->get();
```

## Cross-Table Queries

When the query targets another table, switch context explicitly:

```php
$rows = self::queryTable('user_permissions')
  ->select('user_id', 'permission_level')
  ->where('tenant_id', '=', $tenantID)
  ->get();
```

If the query belongs to a non-default connection, set model connection (or use `onConnection()` on the query object) before executing.

## List Pattern

Typical list flow:

```php
$countQ = (new self())->reset();
$dataQ = (new self())->reset()->select('project_id', 'name');

$totalItems = (int)$countQ->count('*');
$totalPages = (int)ceil($totalItems / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

$rows = $dataQ->paginate($page, $perPage);
```

Return clamped page metadata so the controller can normalize GET URLs.

## Writes and Status Arrays

Do not assume ORM write methods are booleans.

Check returned statuses when behavior depends on them.

Example:

```php
$res = $this->reset()
  ->where('project_id', '=', $projectID)
  ->deleteWhere();
```

## Transactions

Use transactions for multi-step writes that must succeed together:

```php
self::beginTransaction('main');
try {
  // multiple writes
  self::commit('main');
  return ['status' => 'success', 'message' => 'Done'];
} catch (Exception $e) {
  self::rollBack('main');
  error_log('[Model] ' . $e->getMessage());
  return ['status' => 'error', 'message' => 'No se pudo completar la operación.', 'code' => 'operation_failed'];
}
```

Do not assume one transaction can atomically span different engines or connection names.

## Raw Helpers

Use `whereRaw()` only when ORM methods are not enough and always bind values:

```php
$query->whereRaw('media_url LIKE ?', ["uploads/p/{$tenantID}/%"]);
```

Do not concatenate user input into SQL fragments.

## Tenant Context

Tenant or user context should be passed into model methods as arguments from the controller or service.

Do not read tenant state from raw request globals inside the model.
