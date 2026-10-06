# ORM Query Patterns

## Base Table Queries

`reset()` clears query state: filters, ordering, limit/offset, joins, selection, grouping, distinct, eager relations and having. It preserves the current table, model attributes, selected connection and comparison/casting configuration. It is not a fresh model for a new insert. `get()` does not reset the instance automatically.

`newQuery()` returns a clone with the current filters and other state. Use it to branch a base query; call `reset()` on the clone when the new intent must discard those filters. Do not infer a blank query from the method name.

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

`queryTable()` is static and creates a new instance. It retains class defaults, not an earlier instance's `onConnection()` override; select the connection after `queryTable()` when needed. Table/column arguments are code-owned identifiers, not raw request values.

## Empty IN Lists

The current `whereIn('id', [])` and `orWhereIn('id', [])` are no-ops. They do not add `1 = 0` or throw. Existing conditions still apply; without other conditions a read can return the full table. If an empty list means no authorized or selected records, return the caller's established empty/rejection result before building the query.

For update/delete, stop before executing an empty selection. A write's missing-WHERE guard does not help when another filter remains, and preserved model attributes may supply a primary key. Do not rely on an empty IN list as access control. Changing this ORM behavior is a separate compatibility task, not part of using this skill.

## Allowed Ordering

`orderBy()` and `orderByExpr()` interpolate their column/expression argument. Repeated calls append criteria; only case-insensitive `DESC` selects descending order, with other values falling back to `ASC`. Choose columns/expressions from a server-owned allowlist; direction normalization does not make a client-supplied expression safe.

Example inside a method receiving normalized `$sortKey` and `$direction`, with `$query` already scoped:

```php
$allowedSort = ['name' => 'name', 'id' => 'project_id'];
$sortColumn = $allowedSort[$sortKey] ?? 'project_id';
$sortDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
$rows = $query->orderBy($sortColumn, $sortDirection)->get();
```

Keep scope filters before sorting and add a stable tie-breaker for pagination when needed. Use `orderByExpr()` only for an expression defined in code. Preserve the endpoint's existing sort-key vocabulary.

## List Pattern

Typical list flow:

```php
$base = $this->reset(); // apply the same business/scope filters here
$countQ = $base->newQuery();
$dataQ = $base->newQuery()->select('project_id', 'name');

$perPage = max(1, min(100, (int)$perPage));
$page = max(1, (int)$page);

$totalItems = (int)$countQ->count('*');
$totalPages = (int)ceil($totalItems / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

$rows = $dataQ->orderBy('project_id', 'ASC')->paginate($page, $perPage);
```

Return clamped page metadata so the controller can normalize GET URLs.

`paginate()` returns rows only and sets limit/offset; it does not clamp inputs, calculate totals or build `meta`. Apply count and data queries to the same filtered base. New instances can lose a runtime connection override, which is why this example clones the base. Its page-size cap is an example application policy, not an ORM-enforced maximum.

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
$pdo = \DatabaseManager::connection('main');
self::beginTransaction('main');
try {
  // multiple writes
  self::commit('main');
  return ['status' => 'success', 'message' => 'Done'];
} catch (\Throwable $exception) {
  if ($pdo instanceof \PDO && $pdo->inTransaction()) {
    self::rollBack('main');
  }
  throw $exception;
}
```

The owning service or controller handles an expected exception and selects the public operation response. The transaction catch cleans up and rethrows; it does not convert a programming error into a business rejection.

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
