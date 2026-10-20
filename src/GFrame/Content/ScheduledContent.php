<?php
namespace GFrame\Content;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

/** Opt-in publication lifecycle; the application's cron handler owns authorization and batching. */
trait ScheduledContent
{
    protected function publicationStatusColumn(): string { return 'status'; }
    protected function publicationDateColumn(): string { return 'publish_at'; }
    protected function scheduledPublicationStatus(): string { return 'scheduled'; }
    protected function publishedPublicationStatus(): string { return 'published'; }
    protected function cancelledPublicationStatus(): string { return 'draft'; }

    /** Snapshots retain lifecycle data for comparison, but restoring does not publish or reschedule. */
    protected function restoreHistoryFields(array $fields, array $current): array
    {
        foreach ([$this->publicationStatusColumn(), $this->publicationDateColumn()] as $column) {
            if (array_key_exists($column, $fields)) $fields[$column] = $current[$column];
        }
        return $fields;
    }

    /** Rows must come from the server-side due query, never a request body. */
    public function publishScheduledRow(array $row, int $authorID, ?int $tenantID = null, array $scope = []): array
    {
        return $this->publishScheduled($row[$this->primaryKey], (string)$row[$this->revisionColumn()], $authorID, $tenantID, $scope);
    }

    public function dueContent(array $scope = [], ?DateTimeImmutable $now = null): \ORM
    {
        $scope = $this->publicationScope($scope);
        $query = $this->newQuery()->reset();
        foreach ($scope as $field => $value) {
            if ($value === null) $query->whereNull($field); else $query->where($field, '=', $value);
        }
        return $query->where($this->publicationStatusColumn(), '=', $this->scheduledPublicationStatus())
            ->where($this->publicationDateColumn(), '<=', $this->publicationNow($now))
            ->orderBy($this->publicationDateColumn(), 'ASC')->orderBy($this->primaryKey, 'ASC');
    }

    public function schedulePublication(int|string $id, string $expectedRevision, string $publishAt, int $authorID, ?int $tenantID = null, array $scope = []): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $publishAt, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s') !== $publishAt || $publishAt <= gmdate('Y-m-d H:i:s')) throw new InvalidArgumentException('La publicación requiere una fecha UTC futura válida.');
        return $this->writePublication($id, $expectedRevision, [$this->publicationStatusColumn() => $this->scheduledPublicationStatus(), $this->publicationDateColumn() => $publishAt], $authorID, $tenantID, $scope);
    }

    public function cancelPublication(int|string $id, string $expectedRevision, int $authorID, ?int $tenantID = null, array $scope = []): array
    {
        return $this->writePublication($id, $expectedRevision, [$this->publicationStatusColumn() => $this->cancelledPublicationStatus(), $this->publicationDateColumn() => null], $authorID, $tenantID, $scope, [$this->publicationStatusColumn() => $this->scheduledPublicationStatus()]);
    }

    public function publishScheduled(int|string $id, string $expectedRevision, int $authorID, ?int $tenantID = null, array $scope = [], ?DateTimeImmutable $now = null): array
    {
        $row = $this->dueContent($scope, $now)->where($this->primaryKey, '=', $id)->whereExact($this->revisionColumn(), $expectedRevision)->limit(1)->get()[0] ?? null;
        if ($row === null) return ['status' => 'error', 'code' => 'content_conflict', 'message' => 'La publicación cambió, se canceló o todavía no corresponde ejecutarla.'];
        return $this->writePublication($id, $expectedRevision, [$this->publicationStatusColumn() => $this->publishedPublicationStatus(), $this->publicationDateColumn() => null], $authorID, $tenantID, $scope,
            [$this->publicationStatusColumn() => $this->scheduledPublicationStatus(), $this->publicationDateColumn() => $row[$this->publicationDateColumn()]]);
    }

    private function writePublication(int|string $id, string $expectedRevision, array $fields, int $authorID, ?int $tenantID, array $scope, array $conditions = []): array
    {
        $scope = $this->publicationScope($scope);
        if ($authorID <= 0) throw new InvalidArgumentException('El autor debe proceder del contexto autorizado.');
        if ($this instanceof RevisionedContentModel) return $this->saveHistoryConditionally($id, $expectedRevision, $fields, $authorID, $tenantID, $scope, $conditions);
        return $this->saveRevisionConditionally($id, $expectedRevision, $fields, $scope, $conditions);
    }

    private function publicationScope(array $scope): array
    {
        if (!$this instanceof VersionedContentModel || $this->wheres !== []) throw new LogicException('Use un modelo versionado limpio y un ámbito explícito para publicación.');
        $status = $this->publicationStatusColumn(); $date = $this->publicationDateColumn();
        foreach ([$status, $date, ...array_keys($scope)] as $field) {
            if (!is_string($field) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $field)) throw new InvalidArgumentException('Columna de publicación no válida.');
        }
        if (count(array_unique([$status, $date, $this->primaryKey, $this->revisionColumn()])) !== 4 || !in_array($status, $this->fillable, true) || !in_array($date, $this->fillable, true)) throw new LogicException('Declare columnas distintas y fillable para estado y fecha de publicación.');
        foreach ($scope as $field => $value) {
            if (in_array($field, [$status, $date, $this->primaryKey, $this->revisionColumn()], true) || (!is_scalar($value) && $value !== null)) throw new InvalidArgumentException('Ámbito de publicación no válido.');
        }
        if (method_exists($this, 'trashColumn')) {
            $column = $this->trashColumn();
            if (array_key_exists($column, $scope) && $scope[$column] !== null) throw new InvalidArgumentException('No se publica contenido en papelera.');
            $scope[$column] = null;
        }
        return $scope;
    }

    private function publicationNow(?DateTimeImmutable $now): string
    {
        return ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
