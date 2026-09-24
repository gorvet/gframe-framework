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

## Transactions

Keep transaction scope on a single connection:

```php
ORM::beginTransaction('main');
try {
  // writes in main
  ORM::commit('main');
} catch (Exception $e) {
  ORM::rollBack('main');
}
```

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
