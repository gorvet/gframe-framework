<?php

namespace GFrame\Media\Contracts;

use GFrame\Media\MediaScope;

interface MediaRepository
{
    public function createMedia(array $media): int;
    public function findMedia(int $mediaID, MediaScope $scope): ?array;
    public function paginateMedia(int $page, int $perPage, MediaScope $scope, array $filters = []): array;
    public function deleteMedia(int $mediaID, MediaScope $scope): void;
    public function updateMedia(int $mediaID, MediaScope $scope, array $data): void;
    public function usedBytes(MediaScope $scope): int;
    public function paths(MediaScope $scope): array;
    public function attach(int $mediaID, string $relatedType, int $relatedID, string $field = 'content', int $sortOrder = 0): void;
    public function detach(int $mediaID, string $relatedType, int $relatedID, string $field = 'content'): void;
    public function related(string $relatedType, int $relatedID, string $field, MediaScope $scope): array;
}
