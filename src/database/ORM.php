<?php
// core/database/ORM.php
//ver 5-2-26
abstract class ORM {
    protected static $pdo = [];
    protected $table;
    protected $connection = null;
    protected $primaryKey = 'id';
    protected $attributes = [];
    protected $wheres = [];
    protected $orderBy = '';
    protected $limit = null;
    protected $offset = null;
    //protected $timestamps = false;
    protected $casts = [];
    protected $debug = false;
    protected $fillable = [];
    protected $select = '*';
    protected $joins = [];
    protected $groupBy = '';
    protected $havings = []; 
    protected $with = [];
    protected $distinct = false;
    protected $strictCompare = true;
    protected $useJsonCast = true;    // Si usas MySQL 8 (no MariaDB) y quieres boolean/NULL estrictos JSON, pon esto a true.
    protected $lastWrite = null;




    public function __construct($attributes = []) {
    $this->fill($attributes);
    }

    protected static function connect(?string $connectionName = null) {
    $name = static::resolveConnectionName($connectionName);
    self::$pdo[$name] = DatabaseManager::connection($name);
    }

    public static function beginTransaction(?string $connectionName = null) {
    $pdo = self::checkPDO($connectionName);
    $pdo->beginTransaction();
    }
    public static function commit(?string $connectionName = null) {
    $pdo = self::checkPDO($connectionName);
    $pdo->commit();
    }
    public static function rollBack(?string $connectionName = null) {
    $pdo = self::checkPDO($connectionName);
    $pdo->rollBack();
    }


    public function fill(array $attributes) {
    foreach ($attributes as $key => $value) {
        if (empty($this->fillable) || in_array($key, $this->fillable)) {
            $this->attributes[$key] = $value;
        }
    }
    }

    public static function find($id) {
    $instance = new static();
    $sql = "SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = :id LIMIT 1";
    $pdo = $instance->pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    return $data ? new static($data) : null;
    }

    public function useStrictComparison($state = true) {
    $this->strictCompare = $state;
    return $this;
    }

    public function when($condition, callable $truthy, ?callable $falsy = null) {
    if ($condition) {
        $result = $truthy($this, $condition);
        return ($result instanceof self) ? $result : $this;
    }

    if ($falsy !== null) {
        $result = $falsy($this, $condition);
        return ($result instanceof self) ? $result : $this;
    }

    return $this;
    }


    public function where($column, $operator = null, $value = null) {
    if (is_array($column)) {
        foreach ($column as $cond) {
            $this->addWhereCondition("{$cond[0]} {$cond[1]} ?", $cond[2], 'AND');
        }
    } else {
    // Si activaste comparación estricta, le metemos CAST al campo
        $column = $this->applyStrictComparisonToColumn($column);
        $this->addWhereCondition("$column $operator ?", $value, 'AND');
    }

    return $this;
    }

    public function orWhere($column, $operator = null, $value = null) {
    if (is_array($column)) {
        foreach ($column as $cond) {
            $this->addWhereCondition("{$cond[0]} {$cond[1]} ?", $cond[2], 'OR');
        }
    } else {
        $column = $this->applyStrictComparisonToColumn($column);
        $this->addWhereCondition("$column $operator ?", $value, 'OR');
    }

    return $this;
    }

    public function whereIn($column, array $values) {
    return $this->whereInWithBoolean($column, $values, 'AND');
    }

    public function orWhereIn($column, array $values) {
    return $this->whereInWithBoolean($column, $values, 'OR');
    }

    public function whereNull($column) {
    $this->addWhereCondition("$column IS NULL", [], 'AND');
    return $this;
    }

    public function whereNotNull($column) {
    $this->addWhereCondition("$column IS NOT NULL", [], 'AND');
    return $this;
    }

    public function orWhereNull($column) {
    $this->addWhereCondition("$column IS NULL", [], 'OR');
    return $this;
    }

    public function orWhereNotNull($column) {
    $this->addWhereCondition("$column IS NOT NULL", [], 'OR');
    return $this;
    }

    public function whereBetween($column, array $range) {
    return $this->whereBetweenWithBoolean($column, $range, 'AND');
    }

    public function orWhereBetween($column, array $range) {
    return $this->whereBetweenWithBoolean($column, $range, 'OR');
    }

    public function whereRaw($condition, array $params = [], $boolean = 'AND') {
    // OJO: $condition debe venir "limpio" (sin user input directo),
    // los valores SIEMPRE van en $params (binds).
    $this->addWhereCondition($condition, $params, $boolean);
    return $this;
    }

    public function orWhereRaw($condition, array $params = []) {
    return $this->whereRaw($condition, $params, 'OR');
    }

    public function whereLikePattern($column, $pattern, $boolean = 'AND') {
    $pattern = (string)$pattern;
    if ($pattern === '') return $this;
    return $this->whereRaw("$column LIKE ? ESCAPE '\\\\'", [$pattern], $boolean);
    }

    public function orWhereLikePattern($column, $pattern) {
    return $this->whereLikePattern($column, $pattern, 'OR');
    }

    public function whereLike($column, $term) {
    $term = trim((string)$term);
    if ($term === '') return $this;

    $like = '%' . str_replace(['\\','%','_'], ['\\\\','\%','\_'], $term) . '%';
    return $this->whereLikePattern($column, $like, 'AND');
    }

    public function whereAnyLike(array $columns, $term) {
    $term = trim((string)$term);
    if ($term === '' || empty($columns)) return $this;

    $like = '%' . str_replace(['\\','%','_'], ['\\\\','\%','\_'], $term) . '%';

    $parts = [];
    $params = [];
    foreach ($columns as $col) {
        $parts[] = "$col LIKE ? ESCAPE '\\\\'";
        $params[] = $like;
    }

    return $this->whereRaw('(' . implode(' OR ', $parts) . ')', $params, 'AND');
    }

    public function whereGroup(callable $callback) {
    return $this->addNestedWhereGroup($callback, 'AND');
    }

    public function orWhereGroup(callable $callback) {
    return $this->addNestedWhereGroup($callback, 'OR');
    }

    private function whereInWithBoolean($column, array $values, string $boolean) {
    if (empty($values)) return $this; // no hace nada si no hay valores

    // Si strictCompare está activo y la columna es un string, aplicamos CAST
    $column = $this->applyStrictComparisonToColumn($column);

    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    $condition = "$column IN ($placeholders)";

    $this->addWhereCondition($condition, $values, $boolean);
    return $this;
    }

    private function whereBetweenWithBoolean($column, array $range, string $boolean) {
    if (count($range) !== 2) {
        throw new Exception("whereBetween requiere exactamente 2 valores [min, max].");
    }

    $column = $this->applyStrictComparisonToColumn($column);
    $this->addWhereCondition("$column BETWEEN ? AND ?", [$range[0], $range[1]], $boolean);
    return $this;
    }

    private function addNestedWhereGroup(callable $callback, string $boolean = 'AND') {
    $nested = $this->newQuery()->reset();
    $nested->useStrictComparison($this->strictCompare);

    $callback($nested);

    [$whereSql, $params] = $nested->buildWhereClause();
    if ($whereSql === '') return $this;

    $inner = preg_replace('/^\s*WHERE\s+/i', '', $whereSql);
    $this->addWhereCondition('(' . $inner . ')', $params, $boolean);
    return $this;
    }


    public function select(...$columns) {
    if (count($columns) === 1 && $columns[0] === '*') {
        $this->select = '*';
        return $this;
    }
    // Limpieza opcional (por si alguien pone espacios extra)
    $columns = array_map('trim', $columns);

    $this->select = implode(', ', $columns);
    return $this;
    }


    public function orderBy($column, $direction = 'ASC') {
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    if (!empty($this->orderBy)) {
    // ya existe ORDER BY, añade otro criterio
        $this->orderBy .= ", $column $direction";
    } else {
        $this->orderBy = "ORDER BY $column $direction";
    }
    return $this;
    }
    public function orderByExpr($expr, $direction = 'ASC') {
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    if (!empty($this->orderBy)) {
        $this->orderBy .= ", $expr $direction";
    } else {
        $this->orderBy = "ORDER BY $expr $direction";
    }
    return $this;
    }  
    public function selectAll() {
    return $this->select('*');
    }

    public function distinct($state = true) {
    $this->distinct = (bool)$state;
    return $this;
    }



    public function paginate($page = 1, $perPage = 10) {
    $this->limit = $perPage;
    $this->offset = ($page - 1) * $perPage;
    return $this->get();
    }

    public function limit($limit) {
    $this->limit = (int) $limit;
    return $this;
    }

    public function offset($offset) {
    $this->offset = (int) $offset;
    return $this;
    }



    public function toSql(): string {
    [$sql, ] = $this->compileSelectQuery();
    return $sql;
    }

    public function getBindings(): array {
    [, $params] = $this->compileSelectQuery();
    return $params;
    }

    public function get() {

    [$sql, $params] = $this->compileSelectQuery();

    if ($this->debug) {
        echo "<pre style='background:#222;color:#0f0;padding:10px;'>";
        echo "[SQL] $sql\n";
        echo "[Params] " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
        echo "</pre>";
        error_log("[SQL] $sql");
    }

    /*propagacion de cualquier error sql al controlaor*/
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);
    if (!$ok) {
    $info = $stmt->errorInfo(); // [SQLSTATE, driver_code, driver_message]
    throw new Exception("SQL error: {$info[2]} | SQLSTATE: {$info[0]} | SQL: $sql");
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eager loading básico por relaciones definidas en el modelo.
    if (!empty($this->with)) {
    foreach ($results as &$row) {
        $instance = new static($row);
        $instance->connection = $this->resolveCurrentConnectionName();
        foreach ($this->with as $relation) {
            if (!method_exists($instance, $relation)) {
                throw new Exception("Relación '$relation' no existe en " . static::class);
            }
            $row[$relation] = $instance->$relation();
        }
    }
    unset($row);
    }

    return $results;
    }

    public function first() {
    $this->limit = 1;
    $results = $this->get();
    if (!isset($results[0])) {
        return null;
    }

    $model = new static($results[0]);
    $model->connection = $this->resolveCurrentConnectionName();
    return $model;
    }

    public function firstOrFail(string $message = "Record not found", int $code = 404) {
    $row = $this->first();
    if ($row === null) {
        throw new Exception($message, $code);
    }
    return $row;
    }

    public function chunk(int $size, callable $callback): bool {
    if ($size < 1) throw new Exception('chunk requiere un tamaño > 0');
    // Evita problemas de OFFSET en datasets mutables.
    return $this->chunkById($size, $callback, $this->primaryKey);
    }

    public function chunkById(int $size, callable $callback, ?string $column = null): bool {
    if ($size < 1) throw new Exception('chunkById requiere un tamaño > 0');

    $column = $column ?: $this->primaryKey;
    $lastId = null;
    $page = 1;

    while (true) {
        $query = $this->newQuery();
        $query->orderBy = '';
        $query->limit = null;
        $query->offset = null;
        $query->useStrictComparison(false)->orderBy($column, 'ASC')->limit($size);

        if ($lastId !== null) {
            $query->where($column, '>', $lastId);
        }

        $rows = $query->get();
        if (empty($rows)) break;

        $continue = $callback($rows, $page);
        if ($continue === false) return false;

        $last = end($rows);
        if (!is_array($last) || !array_key_exists($column, $last)) {
            throw new Exception("chunkById no encontró la columna '{$column}' en el resultado.");
        }
        $lastId = $last[$column];

        if (count($rows) < $size) break;
        $page++;
    }

    return true;
    }

    public static function all(...$columns) {
    $instance = new static();
    if ($columns) $instance->select(...$columns);
    return $instance->get();
    }


    public function fromTable($table) {
    $this->table = $table;
    return $this;
    }

    public function onConnection(string $connectionName) {
    $connectionName = trim($connectionName);
    if ($connectionName === '') {
        throw new Exception('Connection name cannot be empty.');
    }

    $this->connection = $connectionName;
    return $this;
    }


    public static function queryTable($table) {
    $instance = new static();
    $instance->table = $table;
    $instance->select('*');

    return $instance;
    }

    public static function raw($sql, $params = [], $fetchMode = 'all', ?string $connectionName = null) {
    $pdo = self::checkPDO($connectionName);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    switch ($fetchMode) {
        case 'one': return $stmt->fetch(PDO::FETCH_ASSOC);
        case 'column': return $stmt->fetchColumn();
        case 'count': return $stmt->rowCount();
        case 'none': return true;
        case 'all':
        default: return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    }

    public function insert() {
    $keys = [];
    $params = [];

    foreach ($this->attributes as $key => $value) {
        if (empty($this->fillable) || in_array($key, $this->fillable)) {
            $keys[] = $key;
            $params[] = $this->prepareValueForDB($key, $value);
        }
    }


    $placeholders = array_fill(0, count($keys), '?');
    $sql = "INSERT INTO {$this->table} (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $placeholders) . ")";

    if ($this->debug) error_log("[SQL] $sql");
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $this->attributes[$this->primaryKey] = $pdo->lastInsertId();
    return $this->attributes[$this->primaryKey];
    }



    public function update(?array $data = null) {
    // Si pasas array, usa el modo builder
    if ($data !== null) {
        return $this->updateColumns($data);
    }

    // Modo atributos por PK
    if (!isset($this->attributes[$this->primaryKey])) {
        throw new Exception("No se puede actualizar sin clave primaria");
    }

    $columns = [];
    $params  = [];

    foreach ($this->attributes as $key => $value) {
        if ($key === $this->primaryKey) continue;
        if (!empty($this->fillable) && !in_array($key, $this->fillable)) continue;
        $columns[] = "$key = ?";
        $params[]  = $this->prepareValueForDB($key, $value);
    }

    if (empty($columns)) {
        throw new Exception("No hay columnas que actualizar. Revisa \$fillable o el payload.");
    }

    $pkVal = $this->attributes[$this->primaryKey];

    // 1) matched por PK
    $pdo = $this->pdo();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$this->primaryKey} = ?");
    $stmt->execute([$pkVal]);
    $matched = (int) $stmt->fetchColumn();

    // 2) UPDATE
    $params[] = $pkVal;
    $sql = "UPDATE {$this->table} SET " . implode(', ', $columns) . " WHERE {$this->primaryKey} = ?";

    if ($this->debug) error_log("[SQL] $sql");
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $affected = (int) $stmt->rowCount();

    // 3) Status
    $this->lastWrite = [
        'status'   => ($matched === 0) ? 'not_found' : (($affected > 0) ? 'updated' : 'no_change'),
        'matched'  => $matched,
        'affected' => $affected,
    ];

    // Homogéneo con updateColumns: true solo si cambia algo
    return $this->lastWrite;
    }



    protected function updateRaw($sqlFragment, array $params = []) {
    if (empty($this->wheres)) {
        throw new Exception("No se permite updateRaw sin cláusula WHERE. Protegido para evitar desastres.");
    }

    // Construye WHERE y parámetros del WHERE
    [$whereSql, $paramsWhere] = $this->buildWhereClause();

    // 1) ¿Cuántas filas matchea el WHERE?
    $pdo = $this->pdo();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$this->table}{$whereSql}");
    $stmt->execute($paramsWhere);
    $matched = (int) $stmt->fetchColumn();

    // 2) Ejecuta el UPDATE
    $sql = "UPDATE {$this->table} SET $sqlFragment{$whereSql}";

    if ($this->debug) {
        echo "<pre style='background:#222;color:#0f0;padding:10px;'>";
        echo "[UPDATE RAW] $sql\n";
        echo "[Params SET] " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
        echo "[Params WHERE] " . json_encode($paramsWhere, JSON_UNESCAPED_UNICODE) . "\n";
        echo "</pre>";
    }

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute(array_merge($params, $paramsWhere));
    if (!$ok) {
        $info = $stmt->errorInfo();
    // Status de error
        $this->lastWrite = ['status'=>'error', 'matched'=>$matched, 'affected'=>0];
        throw new Exception("SQL error: {$info[2]} | SQLSTATE: {$info[0]} | SQL: $sql");
    }

    $affected = (int) $stmt->rowCount();

    // 3) Clasifica el resultado
    $this->lastWrite = [
        'status'   => ($matched === 0) ? 'not_found' : (($affected > 0) ? 'updated' : 'no_change'),
        'matched'  => $matched,
        'affected' => $affected,
    ];

    return $this->lastWrite;
    }


    public function updateJson(string $column, array $pathValues): array {
    if (empty($this->wheres) && isset($this->attributes[$this->primaryKey])) {
        $this->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
    }
    if (empty($this->wheres)) {
        throw new Exception("updateJson requiere WHERE o tener seteado el primaryKey en attributes.");
    }

    $fragments = [];
    $params = [];

    foreach ($pathValues as $path => $val) {
    $fragments[] = '?';                       // path
    $params[] = $this->normalizeJsonPath($path);
    $fragments[] = $this->jsonValuePlaceholder($val, $params); // value
    }

    // Si la columna está NULL, inicializa a objeto vacío
    $jsonEmptyObject = $this->dialect()->jsonEmptyObjectExpression();
    $set = "{$column} = JSON_SET(COALESCE({$column}, {$jsonEmptyObject}), " . implode(', ', $fragments) . ")";

    return $this->updateRaw($set, $params);
    }

    public function updateColumns(array $data): array {
    // Requiere WHERE o PK en attributes (fallback)
    if (empty($this->wheres) && !isset($this->attributes[$this->primaryKey])) {
        throw new Exception("updateColumns requiere WHERE o una PK en attributes.");
    }
    if (empty($this->wheres) && isset($this->attributes[$this->primaryKey])) {
        $this->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
    }

    $columns = [];
    $params  = [];

    foreach ($data as $key => $value) {
        if (!empty($this->fillable) && !in_array($key, $this->fillable)) continue;
        $columns[] = "$key = ?";
    $params[]  = $this->prepareValueForDB($key, $value); // respeta casts (incl. json si aplica)
    }

    if (empty($columns)) {
    throw new Exception("No hay columnas para actualizar.");
    }

    return $this->updateRaw(implode(', ', $columns), $params);
    }


    public function lastWrite(): array {
    return $this->lastWrite ?? ['status'=>null, 'matched'=>null, 'affected'=>null];
    }

    public function jsonRemove(string $column, array $paths): array {
    if (empty($this->wheres) && isset($this->attributes[$this->primaryKey])) {
        $this->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
    }
    if (empty($this->wheres)) {
        throw new Exception("jsonRemove requiere WHERE o tener seteado el primaryKey en attributes.");
    }

    $placeholders = [];
    $params = [];
    foreach ($paths as $p) {
        $placeholders[] = '?';
        $params[] = $this->normalizeJsonPath($p);
    }

    $jsonEmptyObject = $this->dialect()->jsonEmptyObjectExpression();
    $set = "{$column} = JSON_REMOVE(COALESCE({$column}, {$jsonEmptyObject}), " . implode(', ', $placeholders) . ")";
    return $this->updateRaw($set, $params);
    }


    public function bulkInsert(array $rows) {
    if (empty($rows)) return [];

    $columns = array_keys($rows[0]);
    $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $allPlaceholders = implode(', ', array_fill(0, count($rows), $placeholders));
    $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES $allPlaceholders";

    $params = [];
    foreach ($rows as $row) {
        foreach ($columns as $col) {
            $params[] = $row[$col];
        }
    }

    if ($this->debug) error_log("[SQL] $sql");
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $firstId = (int) $pdo->lastInsertId();
    $insertedCount = $stmt->rowCount();

    $ids = [];
    for ($i = 0; $i < $insertedCount; $i++) {
        $ids[] = $firstId + $i;
    }

    return $ids;
    }

    public function save() {
    if (isset($this->attributes[$this->primaryKey])) {
        return $this->update();
    } else {
        return $this->insert();
    }
    }

    public function upsert(array $uniqueBy, array $data): array {
    if (empty($uniqueBy)) {
        throw new Exception('upsert requiere al menos una columna única en uniqueBy.');
    }

    $uniqueCols = array_keys($uniqueBy);
    foreach ($uniqueCols as $col) {
        if (!$this->isSafeIdentifier((string)$col)) {
            throw new Exception("Columna inválida en uniqueBy: {$col}");
        }
    }
    if (!$this->hasUniqueIndexForColumns($uniqueCols)) {
        throw new Exception("upsert requiere índice UNIQUE/PRIMARY para columnas: " . implode(', ', $uniqueCols));
    }

    // Fuerza que uniqueBy mande sobre data en columnas repetidas.
    $payload = array_merge($data, $uniqueBy);
    if (empty($payload)) {
        throw new Exception("upsert requiere datos para insertar/actualizar.");
    }

    $columns = array_keys($payload);
    foreach ($columns as $col) {
        if (!$this->isSafeIdentifier((string)$col)) {
            throw new Exception("Nombre de columna inválido en upsert: {$col}");
        }
    }

    $quotedTable = $this->quoteIdentifier($this->table, true);
    $quotedCols = array_map(fn($c) => $this->quoteIdentifier((string)$c), $columns);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));

    $params = [];
    foreach ($columns as $col) {
        $params[] = $this->prepareValueForDB((string)$col, $payload[$col]);
    }

    $updateCols = array_values(array_filter($columns, fn($c) => !array_key_exists($c, $uniqueBy)));
    $probe = new static();
    $probe->connection = $this->resolveCurrentConnectionName();
    $probe->fromTable($this->table)->reset();
    foreach ($uniqueBy as $ukey => $uvalue) {
        $probe->where((string)$ukey, '=', $uvalue);
    }
    $existsBefore = $probe->exists();
    $sql = $this->dialect()->compileUpsertSql(
        $quotedTable,
        $quotedCols,
        $placeholders,
        $updateCols,
        array_keys($uniqueBy),
        fn(string $identifier, bool $allowDot = false): string => $this->quoteIdentifier($identifier, $allowDot)
    );

    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);
    if (!$ok) {
        $info = $stmt->errorInfo();
        $this->lastWrite = ['status' => 'error', 'matched' => null, 'affected' => 0];
        throw new Exception("SQL error: {$info[2]} | SQLSTATE: {$info[0]} | SQL: $sql");
    }

    $affected = (int)$stmt->rowCount();
    if ($affected <= 0) {
        $status = 'no_change';
        $action = 'none';
    } else {
        $status = $existsBefore ? 'updated' : 'inserted';
        $action = $status;
    }
    $id = $pdo->lastInsertId();

    $this->lastWrite = [
        'status'   => $status,
        'matched'  => null,
        'affected' => $affected,
    ];

    return [
        'status'   => $status,
        'matched'  => null,
        'affected' => $affected,
        'action'   => $action,
        'id'       => ($id !== false && $id !== null && $id !== '') ? (int)$id : null,
    ];
    }

    public function delete() {
    if (!isset($this->attributes[$this->primaryKey])) return false;
    $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
    if ($this->debug) error_log("[SQL] $sql");
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$this->attributes[$this->primaryKey]]);
    }

    public function deleteWhere(): array {
    if (empty($this->wheres) && isset($this->attributes[$this->primaryKey])) {
        $this->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
    }
    if (empty($this->wheres)) {
        throw new Exception("deleteWhere requiere WHERE o tener seteado el primaryKey en attributes.");
    }

    [$whereSql, $paramsWhere] = $this->buildWhereClause();

    $pdo = $this->pdo();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$this->table}{$whereSql}");
    $stmt->execute($paramsWhere);
    $matched = (int)$stmt->fetchColumn();

    $sql = "DELETE FROM {$this->table}{$whereSql}";
    if ($this->debug) {
        echo "<pre style='background:#222;color:#0f0;padding:10px;'>";
        echo "[DELETE WHERE] $sql\n";
        echo "[Params WHERE] " . json_encode($paramsWhere, JSON_UNESCAPED_UNICODE) . "\n";
        echo "</pre>";
    }

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($paramsWhere);
    if (!$ok) {
        $info = $stmt->errorInfo();
        $this->lastWrite = ['status'=>'error', 'matched'=>$matched, 'affected'=>0];
        throw new Exception("SQL error: {$info[2]} | SQLSTATE: {$info[0]} | SQL: $sql");
    }

    $affected = (int)$stmt->rowCount();
    $this->lastWrite = [
        'status'   => ($matched === 0) ? 'not_found' : (($affected > 0) ? 'deleted' : 'no_change'),
        'matched'  => $matched,
        'affected' => $affected,
    ];

    return $this->lastWrite;
    }



    public function refresh() {
    if (!isset($this->attributes[$this->primaryKey])) return false;
    $fresh = $this->newQuery()
        ->reset()
        ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
        ->first();
    if ($fresh) $this->fill($fresh->attributes);
    }

    public function hasMany($relatedClass, $pivotTable, $foreignKey, $relatedKey) {
    $relatedInstance = new $relatedClass();
    $sql = "SELECT r.* FROM {$relatedInstance->table} r
    JOIN {$pivotTable} p ON r.{$relatedKey} = p.{$relatedKey}
    WHERE p.{$foreignKey} = ?";
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$this->{$this->primaryKey}]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function belongsTo($relatedClass, $foreignKey, $ownerKey) {
    $relatedInstance = new $relatedClass();
    $sql = "SELECT * FROM {$relatedInstance->table} WHERE {$ownerKey} = ? LIMIT 1";
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$this->attributes[$foreignKey]]);
    return $stmt->fetch(PDO::FETCH_ASSOC); // ← aquí el cambio
    }


    public function join($table, $leftColumn, $operator, $rightColumn, $type = 'INNER') {
    $this->joins[] = "$type JOIN $table ON $leftColumn $operator $rightColumn";
    return $this;
    }

    public function leftJoin($table, $leftColumn, $operator, $rightColumn) {
    return $this->join($table, $leftColumn, $operator, $rightColumn, 'LEFT');
    }

    public function rightJoin($table, $leftColumn, $operator, $rightColumn) {
    return $this->join($table, $leftColumn, $operator, $rightColumn, 'RIGHT');
    }

    public function hasOne($relatedClass, $foreignKey, $localKey) {
    $relatedInstance = new $relatedClass();
    $sql = "SELECT * FROM {$relatedInstance->table} WHERE {$foreignKey} = ? LIMIT 1";
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$this->attributes[$localKey]]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function groupBy($column) {
    $this->groupBy = "GROUP BY $column";
    return $this;
    }

    public function having($column, $operator, $value) {
    $this->havings[] = ["$column $operator ?", $value];
    return $this;
    }

    public function reset() {
    $this->wheres = [];
    $this->orderBy = '';
    $this->limit = null;
    $this->offset = null;
    $this->joins = [];
    $this->select = '*';
    $this->groupBy = '';
    $this->distinct = false;
    $this->with = [];
    $this->havings = [];

    return $this;
    }

    public function count($column = '*', $alias = 'total') {
    return $this->aggregate("COUNT($column)", $alias);
    }

    public function countMultipleWithConditions(array $tablesWithConditions) {
    $subqueries = [];
    $params = [];

    if (empty($tablesWithConditions)) {
        return [];
    }

    foreach ($tablesWithConditions as $table => $condition) {
        if (!$this->isSafeIdentifier((string)$table, true)) {
            throw new Exception("Nombre de tabla inválido: {$table}");
        }

        $whereParts = [];
        $whereParams = [];

        if (!empty($condition) && is_array($condition)) {
            foreach ($condition as $columnExpr => $value) {
                [$column, $operator] = $this->parseColumnAndOperator((string)$columnExpr);
                if (!$this->isSafeIdentifier($column)) {
                    throw new Exception("Nombre de columna inválido: {$column}");
                }

                if (is_array($value)) {
                    if (empty($value)) {
                        $whereParts[] = '1 = 0';
                        continue;
                    }

                    $inPlaceholders = implode(', ', array_fill(0, count($value), '?'));
                    if ($operator === '!=' || $operator === '<>') {
                        $whereParts[] = "$column NOT IN ($inPlaceholders)";
                    } else {
                        $whereParts[] = "$column IN ($inPlaceholders)";
                    }
                    foreach ($value as $v) {
                        $whereParams[] = $v;
                    }
                    continue;
                }

                if ($value === null) {
                    if ($operator === '!=' || $operator === '<>') {
                        $whereParts[] = "$column IS NOT NULL";
                    } else {
                        $whereParts[] = "$column IS NULL";
                    }
                    continue;
                }

                $whereParts[] = "$column $operator ?";
                $whereParams[] = $value;
            }
        }

        $whereSql = '';
        if (!empty($whereParts)) {
            $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
        }

        $alias = preg_replace('/[^a-z0-9_]/i', '_', (string)$table);
        $subqueries[] = "(SELECT COUNT(*) FROM {$table}{$whereSql}) AS {$alias}";
        $params = array_merge($params, $whereParams);
    }

    $sql = "SELECT " . implode(', ', $subqueries);
    $result = $this->raw($sql, $params);

    return $result[0] ?? [];
    }


    public function sum($column, $alias = 'sum') {
    return $this->aggregate("SUM($column)", $alias);
    }

    public function avg($column, $alias = 'avg') {
    return $this->aggregate("AVG($column)", $alias);
    }

    public function max($column, $alias = 'max') {
    return $this->aggregate("MAX($column)", $alias);
    }

    public function min($column, $alias = 'min') {
    return $this->aggregate("MIN($column)", $alias);
    }

    protected function aggregate($expression, $alias) {
    $sql = "SELECT $expression AS $alias FROM {$this->table}";

    //  JOINs
    if (!empty($this->joins)) {
        $sql .= ' ' . implode(' ', $this->joins);
    }

    $params = [];

    // WHERE
    [$whereSql, $whereParams] = $this->buildWhereClause();
    if ($whereSql !== '') {
        $sql .= $whereSql;
        $params = array_merge($params, $whereParams);
    }

    //  GROUP BY
    if (!empty($this->groupBy)) {
        $sql .= " {$this->groupBy}";
    }

    //  HAVING
    if (!empty($this->havings)) {
        $sql .= " HAVING ";
        $havingConditions = [];
        foreach ($this->havings as [$condition, $value]) {
            $havingConditions[] = $condition;
            if (is_array($value)) {
                foreach ($value as $v) $params[] = $v;
            } else {
                $params[] = $value;
            }
        }
        $sql .= implode(' AND ', $havingConditions);
    }

    if ($this->debug) {
        $phCount = substr_count($sql, '?');
        $paramCount = count($params);
        echo "<pre style='background:#222;color:#0f0;padding:10px;'>";
        echo "[SQL] $sql\n";
        echo "[Params] " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
        echo "[Placeholders] $phCount  |  [ParamsCount] $paramCount\n";
        echo "</pre>";
    }

    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);
    if (!$ok) {
    $info = $stmt->errorInfo(); // [SQLSTATE, driver_code, driver_message]
    throw new Exception("SQL error: {$info[2]} | SQLSTATE: {$info[0]} | SQL: $sql");
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result[$alias] ?? null;
    }


    public function pluck($column,$key = null) {
    $query = $this->newQuery()->select($key ? "$column, $key" : $column);
    $rows = $query->get();

    return $key
    ? array_column($rows, $column, $key)
    : array_column($rows, $column);
    }

    public function value(string $column) {
    $query = $this->newQuery()->select($column)->limit(1);
    $rows = $query->get();

    if (!isset($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    if (array_key_exists($column, $rows[0])) {
        return $rows[0][$column];
    }

    $vals = array_values($rows[0]);
    return $vals[0] ?? null;
    }

    public function scalar(string $expression, string $alias = 'scalar') {
    $query = $this->newQuery()->select("$expression AS $alias")->limit(1);
    $rows = $query->get();

    if (!isset($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    return $rows[0][$alias] ?? null;
    }

    public function exists() {
    $sql = "SELECT 1 FROM {$this->table}";

    [$whereSql, $params] = $this->buildWhereClause();
    if ($whereSql !== '') {
        $sql .= $whereSql;
    }

    $sql .= " LIMIT 1";
    $pdo = $this->pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
    }

    public function groupConcat($column, $alias = 'grouped', $separator = ', ') {
    $expression = $this->dialect()->groupConcatExpression((string)$column, (string)$separator);
    return $this->aggregate($expression, $alias);
    }
    public function newQuery() {
    return clone $this;
    }

    public function with(...$relations) {
    $clean = [];
    foreach ($relations as $relation) {
        $r = trim((string)$relation);
        if ($r !== '') $clean[] = $r;
    }
    $this->with = array_values(array_unique(array_merge($this->with, $clean)));
    return $this;
    }

    public function __get($key) {
    $value = $this->attributes[$key] ?? null;
    if (isset($this->casts[$key])) {
        switch ($this->casts[$key]) {
            case 'int':      return (int) $value;
            case 'float':    return (float) $value;
            case 'bool':     return (bool) $value;
            case 'datetime': return new DateTime($value);
            case 'json':
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
            }
            return $value;
            default:
            return $value;
        }
    }
    return $value;
    }



    public function __set($key, $value) {
    $this->attributes[$key] = $value;
    }


    // Codifica arrays/objetos a JSON antes de escribir en la base
    private function prepareValueForDB($key, $value) {
    if (isset($this->casts[$key]) && $this->casts[$key] === 'json') {
        if (is_array($value) || is_object($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
    return $value;  // ya es JSON válido
    }
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $value;
    }


    private function normalizeJsonPath(string $path): string {
    // Admite "a.b.c" o "a->b->c" y lo vuelve "$.a.b.c"
    $path = str_replace('->', '.', $path);
    $path = ltrim($path, '.');
    return '$.' . $path;
    }



    private function jsonValuePlaceholder($val, array &$params): string {
    return $this->dialect()->jsonValuePlaceholder($val, $params, (bool)$this->useJsonCast);
    }

    private function applyStrictComparisonToColumn($column) {
    if ($this->strictCompare && is_string($column) && stripos($column, 'CAST(') === false) {
        return $this->dialect()->castAsStringExpression($column);
    }
    return $column;
    }

    private function addWhereCondition(string $condition, $value = [], string $boolean = 'AND') {
    $boolean = strtoupper($boolean) === 'OR' ? 'OR' : 'AND';
    $this->wheres[] = [
        'boolean' => $boolean,
        'condition' => $condition,
        'value' => $value,
    ];
    return $this;
    }

    private function compileSelectQuery(): array {
    $distinct = $this->distinct ? 'DISTINCT ' : '';
    $sql = "SELECT {$distinct}{$this->select} FROM {$this->table}";
    $sql .= ' ' . implode(' ', $this->joins);

    $params = [];

    [$whereSql, $whereParams] = $this->buildWhereClause();
    if ($whereSql !== '') {
        $sql .= $whereSql;
        $params = array_merge($params, $whereParams);
    }

    if ($this->groupBy) {
        $sql .= " " . $this->groupBy;
    }

    if ($this->havings) {
        $sql .= " HAVING ";
        $havingConditions = [];
        foreach ($this->havings as [$condition, $value]) {
            $havingConditions[] = $condition;
            if (is_array($value)) {
                foreach ($value as $v) $params[] = $v;
            } else {
                $params[] = $value;
            }
        }
        $sql .= implode(' AND ', $havingConditions);
    }

    if ($this->orderBy) {
        $sql .= " " . $this->orderBy;
    }

    if ($this->limit !== null) {
        $sql .= " LIMIT {$this->limit}";
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
    }

    return [$sql, $params];
    }

    private function buildWhereClause(): array {
    if (empty($this->wheres)) {
        return ['', []];
    }

    $parts = [];
    $params = [];
    $isFirst = true;

    foreach ($this->wheres as $entry) {
        if (is_array($entry) && array_key_exists('condition', $entry)) {
            $condition = $entry['condition'];
            $value = $entry['value'] ?? [];
            $boolean = strtoupper((string)($entry['boolean'] ?? 'AND'));
            $boolean = ($boolean === 'OR') ? 'OR' : 'AND';
        } elseif (is_array($entry) && array_key_exists(0, $entry)) {
            $condition = $entry[0];
            $value = $entry[1] ?? [];
            $boolean = 'AND';
        } else {
            continue;
        }

        $parts[] = ($isFirst ? '' : " {$boolean} ") . $condition;
        $isFirst = false;

        if (is_array($value)) {
            foreach ($value as $v) {
                $params[] = $v;
            }
        } else {
            $params[] = $value;
        }
    }

    if (empty($parts)) {
        return ['', []];
    }

    return [' WHERE ' . implode('', $parts), $params];
    }

    private function isSafeIdentifier(string $identifier, bool $allowDot = false): bool {
    $identifier = trim($identifier);
    if ($identifier === '') return false;

    $pattern = $allowDot
    ? '/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/'
    : '/^[A-Za-z_][A-Za-z0-9_]*$/';

    return (bool)preg_match($pattern, $identifier);
    }

    private function parseColumnAndOperator(string $columnExpr): array {
    $expr = trim($columnExpr);
    if ($expr === '') {
        throw new Exception('Columna inválida en condición');
    }

    if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*(=|!=|<>|>|>=|<|<=|LIKE)?$/i', $expr, $m)) {
        throw new Exception("Expresión de columna/operador inválida: {$columnExpr}");
    }

    $column = $m[1];
    $operator = strtoupper($m[2] ?? '=');

    $allowed = ['=','!=','<>','>','>=','<','<=','LIKE'];
    if (!in_array($operator, $allowed, true)) {
        $operator = '=';
    }

    return [$column, $operator];
    }

    private function quoteIdentifier(string $identifier, bool $allowDot = false): string {
    $identifier = trim($identifier);
    if (!$this->isSafeIdentifier($identifier, $allowDot)) {
        throw new Exception("Identificador inválido: {$identifier}");
    }

    if ($allowDot && strpos($identifier, '.') !== false) {
        $parts = explode('.', $identifier);
        return implode('.', array_map(fn($p) => '`' . $p . '`', $parts));
    }

    return '`' . $identifier . '`';
    }

    private function hasUniqueIndexForColumns(array $columns): bool {
    $columns = array_values(array_unique(array_map(fn($c) => strtolower((string)$c), $columns)));
    sort($columns);

    if (empty($columns)) return false;

    $table = (string)$this->table;
    $db = null;
    if (strpos($table, '.') !== false) {
        [$db, $table] = explode('.', $table, 2);
    }

    if (!$this->isSafeIdentifier($table) || ($db !== null && !$this->isSafeIdentifier($db))) {
        throw new Exception("Tabla invalida para validacion de indice unico: {$this->table}");
    }

    $indexes = $this->dialect()->fetchUniqueIndexes(
        $this->pdo(),
        $table,
        $db,
        fn(string $identifier, bool $allowDot = false): string => $this->quoteIdentifier($identifier, $allowDot)
    );

    if (empty($indexes)) {
        return false;
    }

    foreach ($indexes as $colsBySeq) {
        ksort($colsBySeq);
        $idxCols = array_values($colsBySeq);
        $idxColsSorted = $idxCols;
        sort($idxColsSorted);
        if ($idxColsSorted === $columns) {
            return true;
        }
    }

    return false;
    }

    protected function pdo(): PDO {
    return self::checkPDO($this->resolveCurrentConnectionName());
    }

    protected function dialect(): DatabaseDialectInterface {
    return DatabaseManager::dialect($this->resolveCurrentConnectionName());
    }

    protected function resolveCurrentConnectionName(): string {
    if (is_string($this->connection)) {
        $candidate = trim($this->connection);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return static::resolveConnectionName(null);
    }

    protected static function resolveConnectionName(?string $connectionName = null): string {
    if (is_string($connectionName)) {
        $candidate = trim($connectionName);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    try {
        $defaults = (new ReflectionClass(static::class))->getDefaultProperties();
        if (isset($defaults['connection']) && is_string($defaults['connection'])) {
            $candidate = trim((string)$defaults['connection']);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    } catch (ReflectionException $e) {
        // Si falla reflection, se usa la conexion por defecto.
    }

    return DatabaseManager::defaultConnectionName();
    }

    protected static function checkPDO(?string $connectionName = null): PDO {
    $name = static::resolveConnectionName($connectionName);

    if (!array_key_exists($name, self::$pdo)) {
        self::connect($name);
    }

    $pdo = self::$pdo[$name] ?? null;
    if (is_array($pdo) && isset($pdo['status']) && $pdo['status'] === 'error') {
        throw new Exception((string)$pdo['message'], (int)$pdo['code']);
    }
    if (!($pdo instanceof PDO)) {
        throw new Exception("PDO connection is not available for '{$name}'", 500);
    }
    return $pdo;
    }


}
