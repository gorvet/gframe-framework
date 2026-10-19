<?php

namespace GFrame\Content;

final class ContentRevisionModel extends \ORM
{
    protected $table = 'content_revisions';
    protected $primaryKey = 'id';
    protected $fillable = ['resource_key', 'revision', 'author_id', 'created_at', 'fields_json'];

    public function entries(string $key, int $limit): array
    {
        return $this->reset()->where('resource_key', '=', $key)->orderBy('id', 'DESC')->limit($limit)->get();
    }

    public function entry(string $key, int $id): ?array
    {
        return $this->reset()->where('resource_key', '=', $key)->where('id', '=', $id)->limit(1)->get()[0] ?? null;
    }

    public function append(string $key, string $revision, ?int $authorID, array $fields): void
    {
        if ($this->reset()->where('resource_key', '=', $key)->whereExact('revision', $revision)->exists()) return;
        $this->reset()->fill(['resource_key' => $key, 'revision' => $revision, 'author_id' => $authorID,
            'created_at' => gmdate('Y-m-d H:i:s'), 'fields_json' => json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        $this->insert();
    }

    public function prune(string $key, int $limit): void
    {
        $older = $this->reset()->where('resource_key', '=', $key)->orderBy('id', 'DESC')->limit(1)->offset($limit)->get()[0] ?? null;
        if ($older !== null) $this->reset()->useStrictComparison(false)->where('resource_key', '=', $key)->where('id', '<=', (int)$older['id'])->deleteWhere();
    }

    public function clearResource(string $key): int
    {
        return (int)$this->reset()->where('resource_key', '=', $key)->deleteWhere()['affected'];
    }
}
