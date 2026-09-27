<?php

namespace GFrame\Media;

class MediaModel extends \ORM
{
    protected $table = 'media';
    protected $primaryKey = 'media_id';
    protected $fillable = [
        'media_id', 'scope_type', 'scope_id', 'source', 'kind', 'name', 'original_name',
        'path', 'mime_type', 'size_bytes', 'created_at',
        'related_type', 'related_id', 'field', 'sort_order',
    ];

    public function createMedia(array $media): int
    {
        $media['created_at'] = $media['created_at'] ?? date('Y-m-d H:i:s');
        return (int)(new static($media))->insert();
    }

    public function findMedia(int $mediaID, MediaScope $scope): ?array
    {
        $rows = $this->applyScope($this->reset()->where('media_id', '=', $mediaID), $scope)
            ->limit(1)
            ->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function paginateMedia(int $page, int $perPage, MediaScope $scope, array $filters = []): array
    {
        $apply = function (self $query) use ($filters, $scope): self {
            $this->applyScope($query, $scope);
            if (!empty($filters['source'])) {
                $query->where('source', '=', (string)$filters['source']);
            }
            if (!empty($filters['kind']) && $filters['kind'] !== 'all') {
                $query->where('kind', '=', (string)$filters['kind']);
            }
            if (!empty($filters['search'])) {
                $query->whereAnyLike(['name', 'original_name'], (string)$filters['search']);
            }
            return $query;
        };

        $total = (int)$apply($this->reset())->count('*');
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $data = $apply($this->reset())
            ->orderBy('media_id', 'DESC')
            ->paginate($page, $perPage);

        return [
            'data' => array_map(static fn(array $row): array => $row, $data),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $lastPage],
        ];
    }

    public function deleteMedia(int $mediaID, MediaScope $scope): void
    {
        $this->applyScope($this->reset()->where('media_id', '=', $mediaID), $scope)->deleteWhere();
    }

    public function attach(int $mediaID, string $relatedType, int $relatedID, string $field = 'content', int $sortOrder = 0): void
    {
        $query = self::queryTable('media_relations')
            ->where('media_id', '=', $mediaID)
            ->where('related_type', '=', $relatedType)
            ->where('related_id', '=', $relatedID)
            ->where('field', '=', $field);
        if ($query->exists()) {
            $query->update(['sort_order' => max(0, $sortOrder)]);
            return;
        }

        (new static([
            'media_id' => $mediaID,
            'related_type' => $relatedType,
            'related_id' => $relatedID,
            'field' => $field,
            'sort_order' => max(0, $sortOrder),
        ]))->fromTable('media_relations')->insert();
    }

    public function detach(int $mediaID, string $relatedType, int $relatedID, string $field = 'content'): void
    {
        self::queryTable('media_relations')
            ->where('media_id', '=', $mediaID)
            ->where('related_type', '=', $relatedType)
            ->where('related_id', '=', $relatedID)
            ->where('field', '=', $field)
            ->deleteWhere();
    }

    /** @return list<array<string, mixed>> */
    public function related(string $relatedType, int $relatedID, string $field = 'content'): array
    {
        return array_map(
            static fn(array $row): array => $row,
            $this->reset()
                ->select('media.*', 'media_relations.field', 'media_relations.sort_order')
                ->join('media_relations', 'media_relations.media_id', '=', 'media.media_id')
                ->where('media_relations.related_type', '=', $relatedType)
                ->where('media_relations.related_id', '=', $relatedID)
                ->where('media_relations.field', '=', $field)
                ->orderBy('media_relations.sort_order', 'ASC')
                ->get()
        );
    }

    private function applyScope(self $query, MediaScope $scope): self
    {
        $query->where('scope_type', '=', $scope->type());
        if ($scope->id() === null) {
            $query->whereNull('scope_id');
        } else {
            $query->where('scope_id', '=', $scope->id());
        }
        return $query;
    }
}
