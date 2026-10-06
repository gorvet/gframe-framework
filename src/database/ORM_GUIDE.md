# ORM: nota interna de arquitectura

> La documentación de uso del ORM vive en [`docs/orm.md`](../../docs/orm.md). Para aprender a utilizarlo dentro de una funcionalidad completa, consulta también [`docs/guia-desarrollo.md`](../../docs/guia-desarrollo.md) y [`docs/tutorial-productos.md`](../../docs/tutorial-productos.md).

Este archivo permanece junto al código para describir únicamente la arquitectura interna de la capa de datos. La configuración y ejemplos de aplicación no deben mantenerse duplicados aquí.

## Ubicación actual

La implementación vive en `src/database/`:

```text
src/database/
  ORM.php
  DatabaseManager.php
  DatabaseConnectionInterface.php
  MySqlConnection.php
  SqliteConnection.php
  dialects/
```

Las rutas históricas `core/database/...` no describen la estructura actual del paquete.

## Capas

### ORM

`ORM.php` expone la API común utilizada por los modelos:

- `find`, `all`, `get`, `first`, `firstOrFail`;
- `select`, `where`, grupos, `whereIn`, `whereBetween`, `whereLike`;
- `orderBy`, `limit`, `offset`, `paginate`;
- `insert`, `save`, `update`, `updateColumns`, `delete`, `deleteWhere`, `upsert`;
- agregados y relaciones;
- transacciones por conexión.

Las diferencias de motor no deben repartirse arbitrariamente por los modelos.

### Conexiones

`DatabaseManager` resuelve conexiones por nombre y devuelve `PDO`.

Las implementaciones actuales incluidas son MySQL y SQLite.

### Dialectos

`src/database/dialects/` encapsula diferencias SQL como:

- upsert;
- expresiones JSON;
- `group_concat`;
- casteo;
- introspección necesaria para índices/operaciones específicas.

## Configuración actual

La configuración principal del proyecto se genera en:

```text
config/app.php
```

con estructura:

```php
return [
    'database' => [
        'default' => 'main',
        'connections' => [
            'main' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', 'localhost'),
                'port' => env_int('DB_PORT', 3306),
                'database' => env('DB_NAME', ''),
                'username' => env('DB_USER', ''),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'auto_create' => false,
            ],
        ],
    ],
];
```

Para SQLite, el instalador genera una conexión con `driver => sqlite`, `path` y `foreign_keys`.

`LegacyConfigBridge` puede exponer constantes históricas para compatibilidad, pero esas constantes no son la forma recomendada de documentar la configuración de proyectos nuevos.

Consulta [`docs/configuracion.md`](../../docs/configuracion.md) para la configuración soportada.

## Modelo mínimo

```php
final class UserModel extends ORM
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
}
```

Una conexión específica puede declararse mediante `$connection` o seleccionarse con `onConnection()` cuando el caso lo requiera.

## Transacciones

```php
ORM::beginTransaction('main');

try {
    // operaciones sobre la conexión main
    ORM::commit('main');
} catch (Throwable $exception) {
    ORM::rollBack('main');
    throw $exception;
}
```

Una transacción pertenece a una conexión. GFrame no convierte operaciones simultáneas sobre motores independientes en una transacción distribuida.

## Regla de mantenimiento

No añadas aquí ejemplos de configuración, rutas de proyecto o tutoriales que ya estén cubiertos por `docs/`.

Cuando cambie el ORM:

1. actualiza primero el código y sus pruebas;
2. actualiza `docs/orm.md` como referencia pública;
3. modifica este archivo solo si cambió la arquitectura interna de `src/database/`.
