<?php

namespace GFrame\Media;

use RuntimeException;

final class MediaLibraryService
{
    public function __construct(
        private readonly MediaModel $media,
        private readonly MediaStorage $storage,
        private readonly MediaProcessor $processor = new MediaProcessor()
    ) {
    }

    public function registerLocalFile(
        string $filePath,
        string $originalName,
        string $source = 'library',
        ?MediaScope $scope = null
    ): array {
        $scope ??= MediaScope::global();
        if (!is_file($filePath)) {
            return ['status' => 'error', 'code' => 'file_not_found'];
        }

        $extension = $this->storage->normalizeExtension((string)pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, $this->processor->getBlockedExts(), true)) {
            return ['status' => 'error', 'code' => 'extension_not_allowed'];
        }

        $mime = $this->processor->detectMime($filePath);
        $classification = $this->processor->classify($mime, $originalName);
        $kind = (string)($classification['type'] ?? 'docs');
        $allowed = $this->processor->getAllowedByKindMap()[$kind] ?? null;
        if (!is_array($allowed) || !in_array($extension, $allowed['exts'] ?? [], true)) {
            return ['status' => 'error', 'code' => 'media_not_allowed'];
        }

        $directory = $this->storage->uploadDirectory($source, date('Y'), date('m'), $scope);
        $filename = $this->storage->uniqueName($directory['absolute'], $originalName, $extension);
        $destination = $directory['absolute'] . DIRECTORY_SEPARATOR . $filename;
        if (!copy($filePath, $destination)) {
            throw new RuntimeException('No se pudo almacenar el archivo multimedia.');
        }

        $relative = $directory['relative'] . '/' . $filename;
        try {
            $mediaID = $this->media->createMedia([
                'scope_type' => $scope->type(),
                'scope_id' => $scope->id(),
                'source' => $source,
                'kind' => $kind,
                'name' => $filename,
                'original_name' => $originalName,
                'path' => $relative,
                'mime_type' => $mime,
                'size_bytes' => filesize($destination) ?: 0,
            ]);
        } catch (\Throwable $exception) {
            $this->storage->delete($relative);
            throw $exception;
        }

        return ['status' => 'success', 'code' => 'media_created', 'media_id' => $mediaID, 'path' => $relative];
    }

    public function paginate(
        int $page = 1,
        int $perPage = 24,
        array $filters = [],
        ?MediaScope $scope = null
    ): array
    {
        $scope ??= MediaScope::global();
        return ['status' => 'success'] + $this->media->paginateMedia(
            max(1, $page),
            max(1, min(100, $perPage)),
            $scope,
            $filters
        );
    }

    public function delete(int $mediaID, ?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        $media = $this->media->findMedia($mediaID, $scope);
        if ($media === null) {
            return ['status' => 'error', 'code' => 'media_not_found'];
        }

        $this->media->deleteMedia($mediaID, $scope);
        $this->storage->delete((string)($media['path'] ?? ''));
        return ['status' => 'success', 'code' => 'media_deleted'];
    }

    public function attach(
        int $mediaID,
        string $relatedType,
        int $relatedID,
        string $field = 'content',
        int $sortOrder = 0,
        ?MediaScope $scope = null
    ): array {
        $scope ??= MediaScope::global();
        $relatedType = trim($relatedType);
        $field = trim($field);
        if ($relatedType === '' || $relatedID <= 0 || $field === '' || $this->media->findMedia($mediaID, $scope) === null) {
            return ['status' => 'error', 'code' => 'invalid_media_relation'];
        }

        $this->media->attach($mediaID, $relatedType, $relatedID, $field, $sortOrder);
        return ['status' => 'success', 'code' => 'media_attached'];
    }

    public function detach(int $mediaID, string $relatedType, int $relatedID, string $field = 'content'): array
    {
        $this->media->detach($mediaID, trim($relatedType), $relatedID, trim($field));
        return ['status' => 'success', 'code' => 'media_detached'];
    }

    public function related(string $relatedType, int $relatedID, string $field = 'content'): array
    {
        return ['status' => 'success', 'data' => $this->media->related(trim($relatedType), $relatedID, trim($field))];
    }
}
