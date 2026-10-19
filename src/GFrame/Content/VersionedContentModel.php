<?php

namespace GFrame\Content;

use InvalidArgumentException;
use LogicException;

/** Project models retain their own table, permissions and schema migrations. */
abstract class VersionedContentModel extends \ORM
{
    protected function revisionColumn(): string { return 'revision'; }

    /** Scope contains server-authorized equality filters, never request-selected columns. */
    public function saveRevision(int|string $id, string $expectedRevision, array $fields, array $scope = []): array
    {
        return $this->saveRevisionConditionally($id, $expectedRevision, $fields, $scope);
    }

    /** Additional code-owned equality preconditions may include fields being changed. */
    protected function saveRevisionConditionally(int|string $id, string $expectedRevision, array $fields, array $scope = [], array $conditions = []): array
    {
        $revisionColumn = $this->revisionColumn();
        if ($this->wheres !== []) throw new LogicException('Use reset() y pase el ámbito autorizado explícitamente a saveRevision().');
        if ($this->fillable === [] || !in_array($revisionColumn, $this->fillable, true)) {
            throw new LogicException('El modelo debe declarar fillable e incluir su columna de revisión.');
        }
        foreach ([$this->primaryKey, $revisionColumn, ...array_keys($fields), ...array_keys($scope), ...array_keys($conditions)] as $column) {
            if (!is_string($column) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $column)) {
                throw new InvalidArgumentException('Columna de contenido no válida.');
            }
        }
        if ($fields === [] || $expectedRevision === '' || strlen($expectedRevision) > 128) {
            throw new InvalidArgumentException('Se requieren contenido y revisión válidos.');
        }
        foreach ($fields as $column => $value) {
            if (!in_array($column, $this->fillable, true) || $column === $this->primaryKey || $column === $revisionColumn || array_key_exists($column, $scope)) {
                throw new InvalidArgumentException('El guardado no puede cambiar identidad, ámbito ni revisión directamente.');
            }
        }
        if (array_key_exists($this->primaryKey, $scope) || array_key_exists($revisionColumn, $scope)) {
            throw new InvalidArgumentException('La clave y la revisión se reciben como argumentos propios.');
        }
        $query = $this->newQuery()->reset()->where($this->primaryKey, '=', $id)->whereExact($revisionColumn, $expectedRevision);
        foreach ($scope as $column => $value) {
            if ($value === null) $query->whereNull($column);
            elseif (is_scalar($value)) $query->where($column, '=', $value);
            else throw new InvalidArgumentException('El ámbito requiere valores escalares o null.');
        }
        foreach ($conditions as $column => $value) {
            if ($value === null) $query->whereNull($column);
            elseif (is_scalar($value)) $query->where($column, '=', $value);
            else throw new InvalidArgumentException('Las precondiciones requieren valores escalares o null.');
        }
        $revision = bin2hex(random_bytes(16));
        $result = $query->update([...$fields, $revisionColumn => $revision]);
        // A preliminary ORM matched count can race; only the conditional UPDATE decides.
        if ((int)($result['affected'] ?? 0) !== 1) {
            return ['status' => 'error', 'code' => 'content_conflict', 'message' => 'El contenido cambió o ya no está disponible. Conserva tus cambios y recarga antes de guardar.'];
        }
        return ['status' => 'success', 'code' => 'content_saved', 'data' => ['revision' => $revision]];
    }
}
