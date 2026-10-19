<?php

namespace GFrame\Content;

use GFrame\Config\ConfigRepository;
use InvalidArgumentException;
use LogicException;

/** Opt-in history, on the business model's own connection. */
abstract class RevisionedContentModel extends VersionedContentModel
{
    abstract protected function historyFields(): array;

    protected function historyLimit(): int
    {
        $value = ConfigRepository::get('content.revisions.limit', 10);
        if (!is_int($value) || $value < 1) throw new InvalidArgumentException('El límite de revisiones debe ser un entero positivo.');
        return $value;
    }

    public function saveRevision(int|string $id, string $expectedRevision, array $fields, array $scope = []): array
    {
        throw new LogicException('Use saveWithHistory() para conservar autor e historial.');
    }

    public function saveWithHistory(int|string $id, string $expectedRevision, array $fields, int $authorID, ?int $tenantID = null, array $scope = []): array
    {
        return $this->saveHistoryConditionally($id, $expectedRevision, $fields, $authorID, $tenantID, $scope);
    }

    protected function saveHistoryConditionally(int|string $id, string $expectedRevision, array $fields, int $authorID, ?int $tenantID = null, array $scope = [], array $conditions = []): array
    {
        if ($authorID <= 0) throw new InvalidArgumentException('El autor debe proceder del contexto autorizado.');
        $this->validatedFields();
        $limit = $this->historyLimit();
        $old = $this->record($id, $scope);
        if ($old === null || !hash_equals((string)$old[$this->revisionColumn()], $expectedRevision)) return $this->conflict();
        return $this->atomic(function () use ($id, $expectedRevision, $fields, $authorID, $tenantID, $scope, $limit, $old, $conditions): array {
            $saved = parent::saveRevisionConditionally($id, $expectedRevision, $fields, $scope, $conditions);
            if ($saved['status'] !== 'success') return $saved;
            $current = $this->record($id, $scope);
            $history = $this->history(); $key = $this->resourceKey($id, $tenantID, $scope);
            // Preserve the pre-adoption state without inventing its historical author/date.
            $history->append($key, (string)$old[$this->revisionColumn()], null, $this->snapshot($old));
            $history->append($key, $saved['data']['revision'], $authorID, $this->snapshot($current));
            $history->prune($key, $limit);
            return $saved;
        });
    }

    public function revisions(int|string $id, ?int $tenantID = null, array $scope = []): array
    {
        $this->validatedFields();
        if ($this->record($id, $scope) === null) return $this->conflict();
        $rows = $this->history()->entries($this->resourceKey($id, $tenantID, $scope), $this->historyLimit());
        foreach ($rows as &$row) { unset($row['fields_json'], $row['resource_key']); }
        return ['status' => 'success', 'data' => $rows];
    }

    public function compareRevisions(int|string $id, int $leftID, int $rightID, ?int $tenantID = null, array $scope = []): array
    {
        if ($this->record($id, $scope) === null) return $this->conflict();
        $key = $this->resourceKey($id, $tenantID, $scope); $history = $this->history();
        $left = $history->entry($key, $leftID); $right = $history->entry($key, $rightID);
        if ($left === null || $right === null) return $this->missing();
        $a = json_decode($left['fields_json'], true, flags: JSON_THROW_ON_ERROR);
        $b = json_decode($right['fields_json'], true, flags: JSON_THROW_ON_ERROR);
        $changes = [];
        foreach ($this->validatedFields() as $field) {
            $before = $a[$field] ?? null; $after = $b[$field] ?? null;
            $changes[$field] = ['before' => $before, 'after' => $after, 'changed' => $before !== $after];
        }
        return ['status' => 'success', 'data' => $changes];
    }

    public function restoreRevision(int|string $id, int $entryID, string $expectedRevision, int $authorID, ?int $tenantID = null, array $scope = []): array
    {
        if ($this->record($id, $scope) === null) return $this->conflict();
        $entry = $this->history()->entry($this->resourceKey($id, $tenantID, $scope), $entryID);
        if ($entry === null) return $this->missing();
        $fields = json_decode($entry['fields_json'], true, flags: JSON_THROW_ON_ERROR);
        if (array_keys($fields) !== $this->validatedFields()) throw new LogicException('Los campos del historial requieren una migración explícita.');
        return $this->saveWithHistory($id, $expectedRevision, $fields, $authorID, $tenantID, $scope);
    }

    public function clearRevisions(int|string $id, string $expectedRevision, bool $confirmed, ?int $tenantID = null, array $scope = []): array
    {
        if (!$confirmed) return ['status' => 'error', 'code' => 'confirmation_required', 'message' => 'Confirma el borrado de todas las revisiones de este contenido.'];
        $row = $this->record($id, $scope);
        if ($row === null || !hash_equals((string)$row[$this->revisionColumn()], $expectedRevision)) return $this->conflict();
        return $this->atomic(function () use ($id, $expectedRevision, $tenantID, $scope, $row): array {
            // CAS takes the row lock. Its temporary revision is never committed or exposed.
            $field = $this->validatedFields()[0];
            $locked = parent::saveRevision($id, $expectedRevision, [$field => $row[$field]], $scope);
            if ($locked['status'] !== 'success') return $locked;
            $this->scoped($id, $scope)->whereExact($this->revisionColumn(), $locked['data']['revision'])->update([$this->revisionColumn() => $expectedRevision]);
            $count = $this->history()->clearResource($this->resourceKey($id, $tenantID, $scope));
            return ['status' => 'success', 'code' => 'content_revisions_cleared', 'data' => ['deleted' => $count]];
        });
    }

    private function atomic(callable $operation): array
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) throw new LogicException('Esta operación debe ser dueña de la transacción; no admite transacciones anidadas.');
        $pdo->beginTransaction();
        try { $result = $operation(); $pdo->commit(); return $result; }
        catch (\Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    }

    private function validatedFields(): array
    {
        $fields = $this->historyFields();
        if ($fields === [] || count($fields) !== count(array_unique($fields))) throw new LogicException('Declare campos únicos para el historial.');
        foreach ($fields as $field) {
            if (!is_string($field) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $field) || !in_array($field, $this->fillable, true) || in_array($field, [$this->primaryKey, $this->revisionColumn()], true)) throw new LogicException('Campo de historial no válido.');
        }
        return $fields;
    }

    private function snapshot(array $row): array
    {
        $data = []; foreach ($this->validatedFields() as $field) $data[$field] = $row[$field] ?? null; return $data;
    }

    private function scoped(int|string $id, array $scope): \ORM
    {
        if ($this->wheres !== []) throw new LogicException('Limpie el modelo con reset() antes de operar con historial.');
        $query = $this->newQuery()->reset()->where($this->primaryKey, '=', $id);
        foreach ($scope as $field => $value) {
            if (!is_string($field) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $field) || in_array($field, [$this->primaryKey, $this->revisionColumn()], true) || (!is_scalar($value) && $value !== null)) throw new InvalidArgumentException('Ámbito de historial no válido.');
            if ($value === null) $query->whereNull($field); else $query->where($field, '=', $value);
        }
        return $query;
    }

    private function record(int|string $id, array $scope): ?array { return $this->scoped($id, $scope)->limit(1)->get()[0] ?? null; }
    private function history(): ContentRevisionModel { return (new ContentRevisionModel())->onConnection($this->resolveCurrentConnectionName()); }
    private function resourceKey(int|string $id, ?int $tenantID, array $scope): string
    {
        if ($tenantID !== null && $tenantID <= 0) throw new InvalidArgumentException('Tenant no válido.');
        return hash('sha256', json_encode([$this->table, (string)$id, $tenantID], JSON_THROW_ON_ERROR));
    }
    private function conflict(): array { return ['status' => 'error', 'code' => 'content_conflict', 'message' => 'El contenido cambió o ya no está disponible.']; }
    private function missing(): array { return ['status' => 'error', 'code' => 'content_revision_not_found', 'message' => 'La revisión no está disponible para este contenido.']; }
}
