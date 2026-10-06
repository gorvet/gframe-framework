# ORM Connections and Dialects

## Goal

Keep one ORM API for models while delegating engine-specific SQL to dialect classes.

## Layers

- Model layer: `ORM` and model classes (`extends ORM`)
- Connection layer: classes that create `PDO` per driver
- Dialect layer: classes that resolve SQL differences per engine

Model methods should not perform `if mysql/sqlite` branching.

## Connection Selection

Default behavior:

- If model has no `$connection`, ORM uses framework default connection.

Named connection:

```php
class CacheModel extends ORM {
  protected $connection = 'sqlite_cache';
}
```

Runtime override when needed:

```php
$rows = UserModel::queryTable('cache_items')
  ->onConnection('sqlite_cache')
  ->get();
```

`queryTable()` creates a new model instance. Calling it after `onConnection()` would discard the previously selected connection.

`reset()` and `newQuery()` preserve an instance's selected connection. Static helpers such as `find`, `raw`, and transaction methods resolve their explicit connection argument (where available), then the model class default or framework default; they do not inherit a previous object's `onConnection()`. Connection names must exist in project configuration; selecting one does not create or register a driver.

## Transactions

Keep transaction scope on a single connection:

```php
$pdo = \DatabaseManager::connection('main');
ORM::beginTransaction('main');
try {
  // writes in main
  ORM::commit('main');
} catch (\Throwable $exception) {
  if ($pdo instanceof \PDO && $pdo->inTransaction()) {
    ORM::rollBack('main');
  }
  throw $exception;
}
```

This catch owns transaction cleanup only. Let the appropriate outer layer log and handle the failure; do not swallow it or report success after rollback.

The example assumes `main` is configured. Every write inside it must also execute on `main`, through the model default or explicit `onConnection('main')`; merely passing `main` to `beginTransaction` does not redirect model queries. `DatabaseManager::connection` may return a failure array, while ORM execution checks can throw; do not assume every connection result is a PDO.

The static transaction methods are direct PDO begin/commit/rollback calls, without savepoints or nested-transaction management. A method participating in an existing transaction should not start or commit another one blindly. Agree on the transaction owner. For MySQL, DDL can implicitly commit; do not promise rollback of schema changes from a DML recipe. Filesystem and external-service effects are not reversed by PDO rollback either.

Do not assume atomic cross-engine transactions (for example MySQL + SQLite in one business flow).

## Raw SQL Rule

Use ORM methods first.

If raw SQL is required, ensure it is compatible with the active connection and avoid embedding engine checks in model methods.

## Extending to New Engines

When introducing a new engine (e.g. PostgreSQL):

1. Add a connection class for PDO bootstrap.
2. Add a dialect class implementing engine-specific SQL behavior.
3. Register both in the framework connection manager.
4. Add the named connection under `database.connections` in the application's `config/app.php`.

Do not duplicate ORM methods per engine.

Locate `docs/orm.md` in the project's resolved package for the complete API and return types. Keep examples on that version; a copied global skill does not establish engine support or the package location.
