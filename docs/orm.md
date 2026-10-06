# ORM, modelos y dialectos

El ORM de GFrame permite consultar y modificar datos desde modelos PHP mediante una API común. DatabaseManager selecciona la conexión PDO y el dialecto correspondiente.

El ORM no crea automáticamente tus tablas ni aplica permisos de ruta. Debes preparar el esquema y ejecutar las comprobaciones de acceso y negocio antes de operar sobre los datos.

## Un modelo del proyecto

En `app/models/catalogo/ProductModel.php`:

```php
<?php

class ProductModel extends ORM
{
    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $fillable = ['product_id', 'name', 'is_active'];
}
```

La tabla del ejemplo necesita esas columnas; no forma parte del esquema estándar del framework. El modelo puede añadir métodos de negocio orientados a persistencia. Recibe argumentos normalizados, no `$_POST` ni parámetros HTTP sin validar.

`fillable` limita los atributos aceptados por `fill()` y utilizados en ciertas escrituras. Una lista vacía admite todos los atributos, no bloquea la asignación masiva. Define una lista explícita y no confíes en ella como filtro universal de cualquier método de escritura.

## Consultar datos

```php
$products = new ProductModel();
$rows = $products->reset()
    ->select('product_id', 'name')
    ->where('is_active', '=', 1)
    ->orderBy('product_id', 'ASC')
    ->limit(20)
    ->get();
```

`get()` devuelve un array de filas asociativas; sin resultados devuelve un array vacío. `first()` devuelve una instancia del modelo o `null`. `ProductModel::find($id)` busca por la clave primaria y también devuelve un modelo o `null`.

```php
$product = (new ProductModel())->where('product_id', '=', 25)->first();
$name = $product !== null ? $product->name : null;
```

No intercambies estos resultados como si todos fueran arrays. `firstOrFail()` lanza una excepción si no encuentra registro.

### Estado de la consulta

Una instancia conserva filtros, selección, orden y otros ajustes. Usa `reset()` antes de iniciar una intención de consulta nueva sobre una instancia reutilizada. Limpia la consulta, no los atributos del modelo ni la conexión elegida.

`queryTable()` crea otra instancia para consultar una tabla diferente:

```php
$rows = ProductModel::queryTable('categories')
    ->select('category_id', 'name')
    ->get();
```

Úsalo para salir de la tabla base, no para cada consulta del modelo. Si necesitas una conexión distinta, aplica `onConnection()` después de `queryTable()`; de lo contrario, perderías el cambio realizado sobre la instancia anterior.

### Filtros, búsqueda y paginación

La API incluye `where`, `orWhere`, `whereIn`, condiciones NULL, rangos y grupos. `whereGroup()` y `orWhereGroup()` permiten agrupar condiciones sin concatenar SQL del cliente.

```php
$rows = (new ProductModel())->reset()
    ->where('is_active', '=', 1)
    ->whereGroup(function ($query) {
        $query->whereLike('name', 'mesa')->orWhere('product_id', '=', 25);
    })
    ->orderBy('product_id', 'ASC')
    ->paginate(1, 10);
```

`paginate()` ejecuta la consulta y devuelve filas, no un objeto con total de páginas. Calcula el total con otra consulta y valida que página y tamaño sean positivos. Reutiliza los mismos filtros en el conteo y en la selección.

`whereLike()` prepara una búsqueda textual; `whereLikePattern()` permite un patrón explícito. Las reglas SQL de comparación pueden variar por motor y collation. `useStrictComparison()` permite controlar el modo de comparación del ORM, pero no vuelve idénticas todas las reglas de MySQL y SQLite.

Los valores se enlazan a sentencias preparadas. Los nombres de tablas, columnas, expresiones y direcciones de orden deben proceder de código o listas permitidas, nunca directamente de la petición.

## Referencia de consultas

Los métodos de construcción devuelven la instancia para encadenar llamadas. La consulta se ejecuta al pedir resultados.

| Método | Uso |
| --- | --- |
| `select('product_id', 'name')` | Seleccionar columnas; recibe argumentos separados, no un array |
| `selectAll()` | Restablecer la selección a `*` |
| `distinct()` | Evitar filas idénticas en la selección |
| `where('is_active', '=', 1)` | Añadir una condición AND; especificar siempre operador y valor |
| `where([['is_active', '=', 1], ['product_id', '>', 10]])` | Añadir varias condiciones AND |
| `orWhere('product_id', '=', 25)` | Añadir una alternativa OR |
| `whereIn('product_id', [25, 26])` | Filtrar por valores de una lista |
| `whereNull('name')`, `whereNotNull('name')` | Comprobar NULL sin usar `= null` |
| `whereBetween('product_id', [10, 50])` | Rango inclusivo de dos extremos |
| `whereGroup($callback)`, `orWhereGroup($callback)` | Agrupar condiciones entre paréntesis |
| `whereRaw('product_id > ?', [10])` | Condición SQL con parámetros enlazados |
| `orderBy('name', 'ASC')` | Ordenar; las llamadas sucesivas añaden criterios |
| `orderByExpr('LENGTH(name)', 'DESC')` | Ordenar por una expresión definida en código |
| `limit(20)`, `offset(40)` | Limitar filas y desplazar el inicio |
| `when($condition, $truthy, $falsy)` | Aplicar un callback según una condición; el tercero es opcional |
| `fromTable('products')` | Cambiar la tabla de la instancia actual |
| `newQuery()` | Clonar la consulta actual, incluidos sus filtros |

También existen `orWhereIn()`, `orWhereNull()`, `orWhereNotNull()`, `orWhereBetween()` y `orWhereRaw()`. Usa grupos cuando combines AND y OR para que la precedencia represente la regla de negocio.

Un array vacío en `whereIn()` u `orWhereIn()` omite esa condición. Si la lista representa los registros autorizados y está vacía, devuelve un resultado vacío antes de consultar; no la uses como barrera de acceso.

### Búsqueda textual

`whereLike('name', 'mesa')` busca el término en cualquier posición y escapa `%` y `_` para tratarlos como texto. `whereLikePattern('name', 'Mesa%')` utiliza el patrón proporcionado, con sus comodines. Su variante OR es `orWhereLikePattern()`.

`whereAnyLike(['name', 'otra_columna'], $term)` agrupa una búsqueda OR entre columnas. Todas deben existir en el esquema. Un término vacío omite el filtro. La sensibilidad a mayúsculas y acentos depende del motor y de su configuración.

### Resultados escalares y agregados

| Método | Resultado |
| --- | --- |
| `get()` | Array de filas asociativas |
| `first()` / `find($id)` | Modelo o `null` |
| `firstOrFail($message, $code)` | Modelo o excepción |
| `all('product_id', 'name')` | Filas de una nueva instancia, sin filtros anteriores |
| `pluck('name')` | Array de valores de una columna |
| `pluck('name', 'product_id')` | Array indexado por otra columna; claves repetidas se sobrescriben |
| `value('name')` | Valor de la primera fila o `null` |
| `scalar('COUNT(*)', 'total')` | Valor de una expresión con alias o `null` |
| `exists()` | Booleano sobre la tabla y sus WHERE; no incorpora joins |
| `count('*')`, `sum('product_id')`, `avg('product_id')` | Un valor agregado |
| `min('product_id')`, `max('product_id')` | Extremo de una columna |
| `groupConcat('name', 'names', ', ')` | Concatenación mediante el dialecto |

Los agregados incorporan WHERE, joins, agrupación y HAVING, pero devuelven el valor de la primera fila. Para obtener todas las agrupaciones, utiliza `select()` con expresiones, `groupBy()` y `get()`. Las llamadas sucesivas a `groupBy()` reemplazan la agrupación anterior; escribe las columnas juntas si necesitas varias.

```php
$active = (new ProductModel())->where('is_active', '=', 1);
$total = (int) $active->count();
$names = $active->newQuery()->orderBy('name')->pluck('name', 'product_id');
$summary = (new ProductModel())
    ->select('is_active', 'COUNT(*) AS total')
    ->groupBy('is_active')
    ->having('is_active', '=', 1)
    ->get();
```

`countMultipleWithConditions(['products' => ['is_active' => 1], 'categories' => []])` devuelve un conteo por tabla. Sus condiciones usan claves como `product_id >`, listas para IN y `null` para IS NULL. Opera con las tablas indicadas, no con los filtros acumulados de la instancia.

### Listados con total y página válida

Construye una consulta base con los filtros y clónala para separar el conteo del resultado. `paginate()` devuelve únicamente las filas; el modelo del listado debe componer los metadatos.

```php
$base = (new ProductModel())->where('is_active', '=', 1);
$perPage = 10;
$requestedPage = 2;
$totalItems = (int) $base->count();
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = max(1, min($requestedPage, $totalPages));
$rows = $base->newQuery()->orderBy('product_id')->paginate($page, $perPage);
$listing = ['data' => $rows, 'meta' => [
    'page' => $page, 'total_pages' => $totalPages, 'total_items' => $totalItems,
]];
```

Para mostrar y actualizar este resultado desde la vista, consulta [Listados por AJAX](frontend-core.md#listados-por-ajax).

### Procesar registros por lotes

`chunk($size, $callback)` utiliza la clave primaria. `chunkById($size, $callback, $column)` permite otra columna de recorrido. El callback recibe un array de filas y el número de lote; devolver `false` detiene el proceso. El método devuelve `true` si completa el recorrido y `false` si el callback lo interrumpe.

```php
$processedIds = [];
(new ProductModel())->select('product_id', 'name')->chunkById(
    100,
    function (array $rows, int $batch) use (&$processedIds) {
        foreach ($rows as $row) $processedIds[] = $row['product_id'];
    },
    'product_id'
);
```

La columna de recorrido debe estar en la selección y ser única y ordenable. El método avanza con `>` sobre el último valor, conserva los filtros y sustituye orden, límite y offset. Evita modificar esa columna durante el recorrido.

## Insertar, actualizar y borrar

```php
$product = new ProductModel(['name' => 'Mesa', 'is_active' => 1]);
$id = $product->insert();

$result = (new ProductModel())->reset()
    ->where('product_id', '=', $id)
    ->update(['name' => 'Mesa grande']);
```

`insert()` devuelve la clave generada. `update($array)` utiliza el modo de actualización por filtros; `update()` sin argumentos utiliza los atributos y la clave primaria del modelo. Comprueba el resultado de escritura según su contrato: no todos los métodos devuelven un booleano.

`delete()` borra la instancia por su clave primaria y devuelve el resultado de ejecución; sin clave devuelve `false`. `deleteWhere()` utiliza filtros y devuelve `status`, `matched` y `affected`, con estados como `deleted`, `not_found` o `no_change`.

`deleteWhere()` rechaza una operación sin WHERE ni clave primaria disponible. Un WHERE demasiado amplio sigue siendo peligroso: la protección no comprueba que hayas autorizado todos los registros coincidentes.

`updateJson()`, `jsonRemove()`, `bulkInsert()` y `upsert()` cubren operaciones adicionales. Un upsert necesita restricciones únicas compatibles en el esquema; no inventa índices ni garantiza una regla de unicidad ausente.

| Método | Contrato |
| --- | --- |
| `fill($attributes)` | Asignar atributos permitidos; no ejecuta SQL ni devuelve una consulta encadenable |
| `insert()` | Insertar atributos y devolver la clave generada |
| `update()` | Actualizar por la clave primaria de los atributos |
| `update($data)` / `updateColumns($data)` | Actualizar columnas permitidas por WHERE o clave primaria |
| `save()` | Insertar si falta la clave primaria; actualizar si está presente |
| `delete()` | Borrar por clave primaria; devuelve booleano |
| `deleteWhere()` | Borrar por filtros o clave primaria; devuelve un array de estado |
| `refresh()` | Volver a leer los atributos de la instancia por su clave primaria |
| `lastWrite()` | Consultar el último estado registrado; no ejecuta una escritura |

Las actualizaciones devuelven `status`, `matched` y `affected`: `updated`, `not_found` o `no_change`. Los conteos y la detección de cambios dependen de PDO y del motor; `no_change` no equivale a un error de transporte. Las excepciones se gestionan aparte.

`upsert($uniqueBy, $data)` recibe un mapa de columnas y valores únicos, no una lista de nombres. Por ejemplo, `upsert(['product_id' => 25], ['name' => 'Mesa', 'is_active' => 1])`. Requiere un índice UNIQUE o PRIMARY para esas columnas y devuelve además `action` e `id`. No uses ese `id` como garantía de la clave del registro actualizado.

`bulkInsert($rows)` espera filas con las mismas columnas y devuelve IDs calculados a partir de `lastInsertId()` y del número de filas afectadas. No garantiza IDs fiables entre motores o ante huecos y triggers. Tampoco aplica el filtrado de `fillable` ni los casts de `insert()`; prepara explícitamente las filas antes de usarlo.

`updateJson('settings', ['theme' => 'dark'])` modifica rutas de una columna JSON; `jsonRemove('settings', ['theme'])` las elimina. Ambos requieren WHERE o clave primaria y devuelven el contrato de actualización. La columna debe existir y contener JSON válido, o NULL para inicializarlo; comprueba que el motor tenga las funciones JSON necesarias.

### Conversión de atributos

La propiedad protegida `$casts` admite `int`, `float`, `bool`, `datetime` y `json`. Se aplica al leer atributos de una instancia, por ejemplo `$product->is_active`; las filas de `get()` siguen siendo arrays de PDO. Para JSON, utiliza estructuras serializables; la conversión de fechas puede lanzar una excepción ante datos inválidos.

## Conexiones y transacciones

Los modelos sin `$connection` usan la conexión predeterminada. Puedes declarar `protected $connection = 'catalog';` o elegirla en una consulta mediante `onConnection('catalog')`. El nombre debe estar configurado en el proyecto; consulta [Configuración](configuracion.md).

Una transacción solo engloba escrituras realizadas sobre la misma conexión:

```php
ProductModel::beginTransaction('catalog');
try {
    $product = (new ProductModel(['name' => 'Mesa', 'is_active' => 1]))
        ->onConnection('catalog');
    $id = $product->insert();
    ProductModel::commit('catalog');
} catch (Throwable $exception) {
    ProductModel::rollBack('catalog');
    throw $exception;
}
```

El ejemplo supone que `catalog` existe y que el inicio de la transacción fue satisfactorio. En el servicio o controlador, registra el error de forma segura y devuelve el contrato público correspondiente, sin exponer SQL ni credenciales.

No hay transacciones distribuidas entre conexiones ni una API de savepoints en estos métodos. Una operación sobre archivos o un servicio externo tampoco se revierte mediante rollback de PDO.

## Relaciones y joins

`join()` y `leftJoin()` permiten unir tablas. Selecciona columnas sin ambigüedad y comprueba que el esquema y la conexión permitan esa consulta. La existencia de `rightJoin()` no garantiza que cualquier versión de SQLite lo admita.

Los helpers de relaciones no son una copia de Eloquent:

| Método | Resultado y comportamiento |
| --- | --- |
| `belongsTo($class, $foreignKey, $ownerKey)` | Fila relacionada por la clave extranjera, o resultado vacío de PDO |
| `hasOne($class, $foreignKey, $localKey)` | Una fila de la tabla relacionada |
| `hasMany($class, $pivotTable, $foreignKey, $relatedKey)` | Filas mediante una tabla pivote; requiere cuatro argumentos |
| `with('relation')` | Ejecuta el método de relación de cada modelo obtenido y añade su resultado a la fila |

`with()` puede generar una consulta por fila y relación; no lo presentes como carga conjunta optimizada. Los helpers de relación usan la conexión del modelo que inicia la operación, no una unión automática entre conexiones independientes.

## Qué resuelve un dialecto

```text
modelo → ORM → DatabaseManager
                 ├─ conexión → PDO
                 └─ dialecto → expresiones SQL del motor
```

`DatabaseDialectInterface` define expresiones LIKE, JSON, conversión a texto y concatenación agrupada, además de compilación de upsert e inspección de índices únicos. `MySqlDialect` y `SqliteDialect` implementan esas diferencias.

Esto permite mantener una API común sin escribir ramas de motor en cada modelo. No convierte SQL arbitrario ni migra esquemas entre motores. JSON, collations, índices, funciones, versiones y tipos de columna siguen requiriendo comprobación en el motor de destino.

El manager reconoce los drivers implementados en su código. Añadir una entrada PostgreSQL a la configuración no basta: un nuevo motor requiere una conexión que implemente `DatabaseConnectionInterface`, un dialecto que implemente `DatabaseDialectInterface`, registro en DatabaseManager y pruebas. No existe un registro de drivers externos configurable desde una ruta.

## SQL directo y diagnóstico

Usa `whereRaw()` o `raw()` solo cuando la API no cubra la consulta y enlaza los valores:

```php
$rows = ProductModel::raw(
    'SELECT product_id, name FROM products WHERE product_id = ?',
    [25],
    'all'
);
```

`raw()` admite modos `all`, `one`, `column`, `count` y `none`, además de una conexión como cuarto argumento. `count` devuelve el número de filas afectadas de PDO, no reemplaza una consulta SELECT COUNT fiable.

`toSql()` y `getBindings()` permiten inspeccionar la consulta construida. No publiques esos datos en respuestas al visitante. Los errores de conexión o SQL pueden lanzar excepciones; una escritura fallida no siempre llega como array de error.

Para ampliar el comportamiento, crea métodos o hereda modelos desde la aplicación, conservando los contratos de quien los consume. No edites el ORM del paquete para adaptar el nombre de una tabla o una regla exclusiva del proyecto.
