# ORM Multi-Connection + Dialects Guide

Este framework usa un ORM unico (`ORM`) para los modelos, y separa lo especifico de cada motor en capas inferiores.

## Arquitectura

### Capa de modelo (API comun)

- `core/database/ORM.php`
- Los modelos hacen `extends ORM` y usan los mismos metodos (`select`, `where`, `join`, `update`, `upsert`, etc.).

### Capa de conexion (por motor)

- `core/database/DatabaseConnectionInterface.php`
- `core/database/MySqlConnection.php`
- `core/database/SqliteConnection.php`

Estas clases crean `PDO` segun el `driver` configurado.

### Capa de dialecto SQL (por motor)

- `core/database/dialects/DatabaseDialectInterface.php`
- `core/database/dialects/MySqlDialect.php`
- `core/database/dialects/SqliteDialect.php`

Estas clases encapsulan diferencias SQL entre motores:

- `upsert`
- `group_concat`
- expresiones JSON
- casteo string
- introspeccion de indices unicos

### Orquestacion

- `core/database/DatabaseManager.php`

Resuelve por nombre de conexion:

- `connection(name)` -> `PDO`
- `dialect(name)` -> dialecto del motor

Con cache interna por conexion.

## Configuracion

Definir conexiones en `config/Config.php`:

```php
define('DB_DEFAULT_CONNECTION', 'main');
define('DB_CONNECTIONS', [
  'main' => [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'app',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
  ],
  'sqlite_cache' => [
    'driver' => 'sqlite',
    'path' => ABSPATH . 'database/cache.sqlite',
    'foreign_keys' => true,
    'busy_timeout_ms' => 5000,
  ],
]);
```

Reglas:

- Si un modelo no define `$connection`, usa `DB_DEFAULT_CONNECTION`.
- Si una conexion no existe en `DB_CONNECTIONS`, lanza excepcion.

## Uso en modelos

Conexion por defecto:

```php
class UserModel extends ORM {
    protected $table = 'users';
    protected $primaryKey = 'user_id';
}
```

Modelo atado a conexion especifica:

```php
class CacheModel extends ORM {
    protected $connection = 'sqlite_cache';
    protected $table = 'cache_items';
    protected $primaryKey = 'cache_id';
}
```

Cambio de conexion en runtime:

```php
$rows = (new UserModel())
  ->onConnection('sqlite_cache')
  ->queryTable('cache_items')
  ->where('scope', '=', 'tokens')
  ->get();
```

## Transacciones

Por conexion especifica:

```php
ORM::beginTransaction('main');
try {
  // writes en main
  ORM::commit('main');
} catch (Exception $e) {
  ORM::rollBack('main');
  throw $e;
}
```

Nota: no hay transaccion atomica distribuida entre MySQL y SQLite. Si usas dos motores en un mismo flujo, maneja compensacion/logica de consistencia a nivel aplicacion.

## Extender a otro motor (ej. PostgreSQL)

1. Crear `PostgresConnection` implementando `DatabaseConnectionInterface`.
2. Crear `PostgresDialect` implementando `DatabaseDialectInterface`.
3. Registrar ambos en `DatabaseManager::resolveConnection()` y `DatabaseManager::resolveDialect()`.
4. Agregar conexion en `DB_CONNECTIONS` con `'driver' => 'pgsql'`.

Con eso, `ORM` no necesita condicionales por motor.

## Checklist rapido

- Modelo siempre extiende `ORM`.
- Definir `$connection` solo cuando no use la default.
- No mezclar SQL de motor en el modelo.
- Diferencias SQL se resuelven en dialectos.
- Mantener `DB_CONNECTIONS` como unica fuente de verdad.
