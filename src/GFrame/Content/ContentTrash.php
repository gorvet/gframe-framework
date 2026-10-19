<?php

namespace GFrame\Content;

use InvalidArgumentException;
use LogicException;

/** Opt-in lifecycle for VersionedContentModel or RevisionedContentModel descendants. */
trait ContentTrash
{
    protected function trashColumn(): string { return 'deleted_at'; }

    public function activeContent(array $scope = []): \ORM { return $this->trashQuery(false, $scope); }
    public function trashedContent(array $scope = []): \ORM { return $this->trashQuery(true, $scope); }

    public function trashContent(int|string $id, string $expectedRevision, array $scope = []): array
    {
        return $this->changeTrashState($id, $expectedRevision, false, $scope);
    }

    public function restoreContent(int|string $id, string $expectedRevision, array $scope = []): array
    {
        return $this->changeTrashState($id, $expectedRevision, true, $scope);
    }

    public function deleteTrashedContent(int|string $id, string $expectedRevision, bool $confirmed, array $scope = []): array
    {
        if (!$confirmed) return ['status' => 'error', 'code' => 'confirmation_required', 'message' => 'Confirma el borrado definitivo de este contenido.'];
        $query = $this->trashTarget($id, $expectedRevision, true, $scope);
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) throw new LogicException('El borrado definitivo debe ser dueño de la transacción.');
        $pdo->beginTransaction();
        try {
            // CAS acquires the row lock before application-owned relation cleanup.
            $revision = bin2hex(random_bytes(16));
            $locked = $query->update([$this->revisionColumn() => $revision]);
            if ((int)($locked['affected'] ?? 0) !== 1) { $pdo->rollBack(); return $this->trashConflict(); }
            $target = $this->trashTarget($id, $revision, true, $scope);
            $row = $target->newQuery()->limit(1)->get()[0] ?? null;
            if ($row === null) throw new LogicException('El contenido bloqueado dejó de estar disponible.');
            $this->deleteTrashedRelations($row);
            $deleted = $target->deleteWhere();
            if ((int)($deleted['affected'] ?? 0) !== 1) throw new LogicException('No se pudo completar el borrado definitivo.');
            $pdo->commit();
            return ['status' => 'success', 'code' => 'content_deleted', 'data' => ['deleted' => 1]];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    /** Override for SQL relations on this same connection; never perform filesystem/network effects here. */
    protected function deleteTrashedRelations(array $row): void {}

    private function changeTrashState(int|string $id, string $expectedRevision, bool $restoring, array $scope): array
    {
        $query = $this->trashTarget($id, $expectedRevision, $restoring, $scope);
        $revision = bin2hex(random_bytes(16));
        $result = $query->update([$this->trashColumn() => $restoring ? null : gmdate('Y-m-d H:i:s'), $this->revisionColumn() => $revision]);
        if ((int)($result['affected'] ?? 0) !== 1) return $this->trashConflict();
        return ['status' => 'success', 'code' => $restoring ? 'content_restored' : 'content_trashed', 'data' => ['revision' => $revision]];
    }

    private function trashTarget(int|string $id, string $expectedRevision, bool $trashed, array $scope): \ORM
    {
        if ($expectedRevision === '' || strlen($expectedRevision) > 128) throw new InvalidArgumentException('Se requiere una revisión válida.');
        return $this->trashQuery($trashed, $scope)->where($this->primaryKey, '=', $id)->whereExact($this->revisionColumn(), $expectedRevision);
    }

    private function trashQuery(bool $trashed, array $scope): \ORM
    {
        if (!$this instanceof VersionedContentModel) throw new LogicException('La papelera requiere un modelo de contenido versionado.');
        if ($this->wheres !== []) throw new LogicException('Use reset() y pase el ámbito autorizado explícitamente a la papelera.');
        $column = $this->trashColumn();
        foreach ([$this->primaryKey, $this->revisionColumn(), $column, ...array_keys($scope)] as $field) {
            if (!is_string($field) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $field)) throw new InvalidArgumentException('Columna de papelera no válida.');
        }
        if (count(array_unique([$column, $this->primaryKey, $this->revisionColumn()])) !== 3 || !in_array($column, $this->fillable, true) || !in_array($this->revisionColumn(), $this->fillable, true)) throw new LogicException('Declare columnas distintas y fillable para papelera y revisión.');
        $query = $this->newQuery()->reset();
        foreach ($scope as $field => $value) {
            if (in_array($field, [$this->primaryKey, $column, $this->revisionColumn()], true) || (!is_scalar($value) && $value !== null)) throw new InvalidArgumentException('Ámbito de papelera no válido.');
            if ($value === null) $query->whereNull($field); else $query->where($field, '=', $value);
        }
        return $trashed ? $query->whereNotNull($column) : $query->whereNull($column);
    }

    private function trashConflict(): array { return ['status' => 'error', 'code' => 'content_conflict', 'message' => 'El contenido cambió, no está en el estado esperado o ya no está disponible.']; }
}
