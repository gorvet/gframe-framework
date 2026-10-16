<?php

namespace GFrame\Content;

use PDOException;

final class ContentDraftModel extends \ORM
{
    protected $table = 'content_autosaves';
    protected $primaryKey = 'draft_key';
    protected $fillable = ['draft_key', 'version', 'content', 'base_version', 'discarded', 'updated_at', 'expires_at'];

    public function readDraft(string $key): ?array
    {
        return $this->reset()->where('draft_key', '=', $key)->limit(1)->get()[0] ?? null;
    }

    /** The affected count, not the preliminary matched count, decides the CAS winner. */
    public function compareAndSwap(string $key, ?string $expected, array $fields): bool
    {
        if ($expected !== null) {
            $result = $this->reset()->where('draft_key', '=', $key)->where('version', '=', $expected)->update($fields);
            return (int)($result['affected'] ?? 0) === 1;
        }
        try {
            $model = new self(['draft_key' => $key, ...$fields]);
            // Preserve an injected/named connection on the new insert instance.
            if ($this->connection !== null) $model->onConnection($this->connection);
            $model->insert();
            return true;
        } catch (PDOException $exception) {
            if (in_array((string)$exception->getCode(), ['23000', '23505'], true) && $this->readDraft($key) !== null) return false;
            throw $exception;
        }
    }
}
