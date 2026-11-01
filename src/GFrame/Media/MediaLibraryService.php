<?php

namespace GFrame\Media;

use Exception;
use GFrame\Config\ConfigRepository;
use GFrame\Media\Contracts\MediaRepository;

class MediaLibraryService
{
    public function __construct(
        protected readonly MediaRepository $media,
        protected readonly MediaStorage $storage,
        protected readonly MediaProcessor $processor = new MediaProcessor(),
        protected readonly RemoteMediaInspector $remote = new RemoteMediaInspector(),
        protected readonly ?MediaAccessPolicy $access = null
    ) {
    }

    public function registerRemoteUrl(string $url, string $name = '', string $source = 'library', ?MediaScope $scope = null, array $uploader = []): array
    {
        $scope ??= MediaScope::global();
        try {
            if ($this->visibility($this->normalizeSource($source), null) === 'private') return $this->error('private_hotlink_not_allowed');
            $inspection = $this->remote->inspect($url, $this->processor);
            if (($inspection['status'] ?? '') !== 'success') return $this->error((string)($inspection['code'] ?? 'hotlink_probe_failed'));
            $url = (string)$inspection['url'];
            $extension = (string)$inspection['extension'];
            $name = mb_substr(trim(strip_tags($name)), 0, 255, 'UTF-8');
            if ($name === '') $name = basename((string)(parse_url($url, PHP_URL_PATH) ?: ''));
            if ($name === '') $name = 'enlace-' . date('Ymd-His') . '.' . $extension;
            $recordPath = 'remote/' . bin2hex(random_bytes(16));
            $mediaID = $this->media->createMedia([
                'scope_type' => $scope->type(), 'scope_id' => $scope->id(), 'visibility' => 'public',
                'source' => $this->normalizeSource($source), 'kind' => $inspection['kind'],
                'name' => $name, 'original_name' => $name, 'path' => $recordPath, 'remote_url' => $url,
                'mime_type' => $inspection['mime'], 'size_bytes' => 0, 'alt_text' => '',
                'metadata_json' => $this->encodeJson(['origin' => 'hotlink', 'host' => $inspection['host'], 'remote_size_bytes' => $inspection['size']] + $this->uploaderMetadata($uploader)),
                'variants_json' => '{}', 'status' => 'ready',
            ]);
            return ['status' => 'success', 'code' => 'media_created', 'data' => ['media_id' => $mediaID, 'path' => $recordPath, 'remote_url' => $url, 'origin' => 'hotlink']];
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_create_failed');
        }
    }

    public function registerLocalFile(string $filePath, string $originalName, string $source = 'library', ?MediaScope $scope = null, array $uploader = [], ?string $visibility = null): array
    {
        $scope ??= MediaScope::global();
        if (!is_file($filePath)) {
            return $this->error('file_not_found');
        }

        try {
            $source = $this->normalizeSource($source);
            $visibility = $this->visibility($source, $visibility);
            if (!$this->allowed(['visibility' => $visibility, 'source' => $source], 'add')) return $this->error('media_forbidden');
            $size = (int)(filesize($filePath) ?: 0);
            $maxBytes = $this->processor->getMaxUploadBytes();
            if ($size <= 0 || $size > $maxBytes) {
                return $this->error('file_size_not_allowed');
            }
            $quotaBytes = max(0, (int)ConfigRepository::get('media.quota_bytes', 0));
            if ($quotaBytes > 0 && $this->media->usedBytes($scope) + $size > $quotaBytes) {
                return $this->error('media_quota_exceeded');
            }

            $extension = $this->storage->normalizeExtension((string)pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '' || in_array($extension, $this->processor->getBlockedExts(), true)) {
                return $this->error('extension_not_allowed');
            }

            $kind = $this->kindForExtension($extension);
            if ($kind === null) {
                return $this->error('media_not_allowed');
            }
            $allowed = $this->processor->getAllowedByKindMap()[$kind];
            $mime = $this->processor->detectMime($filePath);
            $validation = $kind === 'images'
                ? $this->processor->validateImageUpload($filePath, (array)$allowed['mimes'])
                : $this->processor->validateNonImageMime($filePath, $kind, $mime, $extension, (array)$allowed['mimes']);
            if (empty($validation['ok'])) {
                return $this->error((string)($validation['code'] ?? 'mime_not_allowed'));
            }
            $mime = (string)($validation['mime'] ?? $mime);
            if ($kind === 'images' && (string)($validation['ext'] ?? '') !== $extension) {
                return $this->error('extension_mime_mismatch');
            }

            $source = $this->normalizeSource($source);
            $directory = $this->storage->uploadDirectory($source, date('Y'), date('m'), $scope, $visibility);
            $filename = $this->storage->uniqueName($directory['absolute'], $visibility === 'private' ? bin2hex(random_bytes(16)) : $originalName, $extension);
            $destination = $directory['absolute'] . DIRECTORY_SEPARATOR . $filename;
            if (!copy($filePath, $destination)) {
                return $this->error('media_storage_failed');
            }

            $relative = $directory['relative'] . '/' . $filename;
            $variants = [];
            $metadata = $this->uploaderMetadata($uploader);
            if ($kind === 'images') {
                $metadata += ['width' => (int)($validation['w'] ?? 0), 'height' => (int)($validation['h'] ?? 0), 'extension' => $extension];
                $variants = $this->processor->generateVariants(
                    $directory['absolute'], $directory['relative'], $destination, $filename, $extension, $source
                );
            }
            $storedBytes = (int)(filesize($destination) ?: $size);
            foreach (array_unique($variants) as $variantPath) {
                $variantAbsolute = $this->storage->absolute((string)$variantPath, $visibility);
                $storedBytes += is_file($variantAbsolute) ? (int)(filesize($variantAbsolute) ?: 0) : 0;
            }
            if ($quotaBytes > 0 && $this->media->usedBytes($scope) + $storedBytes > $quotaBytes) {
                $this->deletePaths(array_merge([$relative], array_values($variants)), $visibility);
                return $this->error('media_quota_exceeded');
            }
            try {
                $mediaID = $this->media->createMedia([
                    'scope_type' => $scope->type(),
                    'scope_id' => $scope->id(),
                    'visibility' => $visibility,
                    'source' => $source,
                    'kind' => $kind,
                    'name' => $filename,
                    'original_name' => basename($originalName),
                    'path' => $relative,
                    'mime_type' => $mime,
                    'size_bytes' => $storedBytes,
                    'alt_text' => '',
                    'metadata_json' => $this->encodeJson($metadata),
                    'variants_json' => $this->encodeJson($variants),
                    'status' => 'ready',
                ]);
            } catch (Exception $exception) {
                $this->deletePaths(array_merge([$relative], array_values(array_unique($variants))), $visibility);
                throw $exception;
            }

            return ['status' => 'success', 'code' => 'media_created', 'data' => ['media_id' => $mediaID, 'path' => $relative, 'variants' => $variants, 'visibility' => $visibility]];
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_create_failed');
        }
    }

    public function paginate(int $page = 1, int $perPage = 24, array $filters = [], ?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        try {
            if ($this->access !== null) $filters = array_merge($filters, $this->access->filters());
            $filters['search'] = mb_substr(trim((string)($filters['search'] ?? '')), 0, 120, 'UTF-8');
            $source = trim((string)($filters['source'] ?? ''));
            $filters['source'] = $source === '' || $source === 'all' ? '' : $this->normalizeSource($source);
            $filters['kind'] = $this->normalizeKind((string)($filters['kind'] ?? 'all'));
            $filters['sort'] = in_array($filters['sort'] ?? '', ['newest', 'oldest', 'name_asc', 'name_desc', 'size_asc', 'size_desc'], true)
                ? $filters['sort'] : 'newest';
            $view = ($filters['view'] ?? '') === 'list' ? 'list' : 'grid';
            $filters['ym'] = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)($filters['ym'] ?? '')) === 1
                ? (string)$filters['ym'] : '';
            $response = ['status' => 'success', 'code' => 'media_loaded'] + $this->media->paginateMedia(
                max(1, $page),
                max(1, min(100, $perPage)),
                $scope,
                $filters
            );
            $response['meta'] = (array)($response['meta'] ?? []) + [
                'max_upload_bytes' => $this->processor->getMaxUploadBytes(),
                'source' => $filters['source'] ?: 'all', 'kind' => $filters['kind'],
                'ym' => $filters['ym'] ?: 'all', 'q' => $filters['search'],
                'sort' => $filters['sort'], 'view' => $view,
                'visibility' => in_array($filters['visibility'] ?? '', ['public', 'private'], true) ? $filters['visibility'] : 'all',
            ];
            $response['filters'] = method_exists($this->media, 'filterOptions')
                ? $this->media->filterOptions($scope, $filters) : ['sources' => [], 'dates' => []];
            return $response;
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_list_failed');
        }
    }

    public function delete(int $mediaID, ?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        try {
            $media = $this->media->findMedia($mediaID, $scope);
            if ($media === null) {
                return $this->error('media_not_found');
            }
            if (!$this->allowed($media, 'delete')) return $this->error('media_not_found');
            $variants = $this->decodeJson((string)($media['variants_json'] ?? ''));
            if (empty($media['remote_url'])) {
                $this->deletePaths(array_merge([(string)($media['path'] ?? '')], array_values($variants)), (string)($media['visibility'] ?? 'public'));
            }
            $this->media->deleteMedia($mediaID, $scope);
            return ['status' => 'success', 'code' => 'media_deleted'];
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_delete_failed');
        }
    }

    public function details(int $mediaID, ?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        try {
            $media = $this->media->findMedia($mediaID, $scope);
            if ($media === null || !$this->allowed($media, 'view')) return $this->error('media_not_found');
            $media['metadata'] = $this->decodeJson((string)($media['metadata_json'] ?? ''));
            $media['variants'] = $this->decodeJson((string)($media['variants_json'] ?? ''));
            unset($media['metadata_json'], $media['variants_json']);
            return ['status' => 'success', 'code' => 'media_details_loaded', 'data' => $media];
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_details_failed');
        }
    }

    public function updateMetadata(int $mediaID, array $input, ?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        try {
            $media = $this->media->findMedia($mediaID, $scope);
            if ($media === null || !$this->allowed($media, 'edit')) return $this->error('media_not_found');
            $name = mb_substr(trim(strip_tags((string)($input['original_name'] ?? ''))), 0, 255, 'UTF-8');
            $alt = mb_substr(trim(strip_tags((string)($input['alt_text'] ?? ''))), 0, 255, 'UTF-8');
            if ($name === '') return $this->error('invalid_media_data');
            $this->media->updateMedia($mediaID, $scope, ['original_name' => $name, 'alt_text' => $alt]);
            return ['status' => 'success', 'code' => 'media_updated'];
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_update_failed');
        }
    }

    public function ingestBase64(string $base64, string $originalName, ?MediaScope $scope = null, array $uploader = [], ?string $visibility = null): array
    {
        $scope ??= MediaScope::global();
        $base64 = preg_replace('/^data:[^;]+;base64,/i', '', trim($base64)) ?? '';
        $binary = base64_decode(preg_replace('/\s+/', '', $base64) ?? '', true);
        if (!is_string($binary) || $binary === '') return $this->error('invalid_base64_media');
        $temporary = tempnam(sys_get_temp_dir(), 'gfm_');
        if ($temporary === false) return $this->error('media_temporary_failed');
        try {
            if (file_put_contents($temporary, $binary) === false) return $this->error('media_temporary_failed');
            return $this->registerLocalFile($temporary, $originalName, 'generated', $scope, $uploader, $visibility);
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_create_failed');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    public function quota(?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        try {
            $used = $this->media->usedBytes($scope);
            $limit = max(0, (int)ConfigRepository::get('media.quota_bytes', 0));
            return ['status' => 'success', 'code' => 'media_quota_loaded', 'data' => [
                'used_bytes' => $used, 'limit_bytes' => $limit, 'available_bytes' => $limit > 0 ? max(0, $limit - $used) : null,
            ]];
        } catch (Exception $exception) {
            return $this->failure($exception, 'media_quota_failed');
        }
    }

    protected function uploaderMetadata(array $uploader): array
    {
        $id = (int)($uploader['id'] ?? 0);
        if ($id <= 0) return [];
        return ['uploader' => ['id' => $id, 'name' => mb_substr(trim(strip_tags((string)($uploader['name'] ?? ''))), 0, 255, 'UTF-8')]];
    }

    private function kindForExtension(string $extension): ?string
    {
        foreach ($this->processor->getAllowedByKindMap() as $kind => $allowed) {
            if (in_array($extension, (array)($allowed['exts'] ?? []), true)) {
                return (string)$kind;
            }
        }
        return null;
    }

    private function normalizeKind(string $kind): string
    {
        $kind = mb_strtolower(trim($kind), 'UTF-8');
        $kind = ['image' => 'images', 'audio' => 'audios', 'video' => 'videos', 'document' => 'docs'][(string)$kind] ?? $kind;
        return in_array($kind, ['all', ...$this->processor->getAllowedByKind()], true) ? $kind : 'all';
    }

    private function normalizeSource(string $source): string
    {
        $source = mb_strtolower(trim($source), 'UTF-8');
        $source = preg_replace('/[^a-z0-9_-]+/', '-', $source) ?: '';
        return trim($source, '-') !== '' ? trim($source, '-') : 'library';
    }

    private function error(string $code): array
    {
        return ['status' => 'error', 'code' => $code];
    }

    private function failure(Exception $exception, string $code): array
    {
        error_log('[GFrame Media] ' . $exception->getMessage());
        return $this->error($code);
    }

    private function encodeJson(array $data): string
    {
        return (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function deletePaths(array $paths, string $visibility = 'public'): void
    {
        foreach (array_unique(array_filter($paths, 'is_string')) as $path) {
            if ($path !== '') $this->storage->delete($path, $visibility);
        }
    }

    private function visibility(string $source, ?string $visibility): string
    {
        $visibility ??= (string)ConfigRepository::get('media.sources.' . $source . '.visibility', ConfigRepository::get('media.visibility', 'public'));
        $this->storage->validateVisibility($visibility);
        return $visibility;
    }

    private function allowed(array $media, string $action): bool
    {
        return ($media['visibility'] ?? 'public') === 'public' || $this->access === null
            || ($this->access->allows((string)$media['source'], 'view') && $this->access->allows((string)$media['source'], $action));
    }

    public function fileResponse(int $mediaID, ?MediaScope $scope = null, string $variant = ''): \GFrame\Http\FileResponse
    {
        $result = $this->details($mediaID, $scope);
        if (($result['status'] ?? '') !== 'success') throw new \RuntimeException('El archivo no está disponible.');
        $media = $result['data'];
        if (!empty($media['remote_url'])) throw new \RuntimeException('El archivo no es local.');
        $path = $variant === '' ? $media['path'] : ($media['variants'][$variant] ?? null);
        if (!is_string($path)) throw new \RuntimeException('La variante no está disponible.');
        return new \GFrame\Http\FileResponse($this->storage->absolute($path, (string)($media['visibility'] ?? 'public')),
            (string)$media['original_name'], (string)$media['mime_type'], true);
    }

    public function issueDownload(int $mediaID, ?MediaScope $scope = null, array $options = []): array
    {
        $scope ??= MediaScope::global();
        $result = $this->details($mediaID, $scope);
        $media = $result['data'] ?? [];
        if (($media['visibility'] ?? '') !== 'private' || !$this->allowed($media, 'share')) {
            throw new \RuntimeException('El archivo no está disponible para compartir.');
        }
        $options['context'] = ['media_id' => $mediaID, 'scope_type' => $scope->type(), 'scope_id' => $scope->id()];
        return (new \GFrame\Files\PrivateDownloadService($this->storage->privateFiles()))->issue((string)$media['path'], (string)$media['original_name'], $options);
    }

    public function revokeDownload(int $mediaID, string $grantID, ?MediaScope $scope = null): void
    {
        $scope ??= MediaScope::global();
        $result = $this->details($mediaID, $scope);
        $media = $result['data'] ?? [];
        if (($media['visibility'] ?? '') !== 'private' || !$this->allowed($media, 'share')) throw new \RuntimeException('Autorización inválida.');
        (new \GFrame\Files\PrivateDownloadService($this->storage->privateFiles()))->revoke($grantID,
            static fn(array $grant): bool => ($grant['context']['media_id'] ?? null) === $mediaID
                && ($grant['context']['scope_type'] ?? null) === $scope->type() && ($grant['context']['scope_id'] ?? null) === $scope->id());
    }

    /** Moves originals and variants; rollback restores the previous physical paths on failure. */
    public function changeVisibility(int $mediaID, string $visibility, ?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        $moved = [];
        try {
            $this->storage->validateVisibility($visibility);
            $media = $this->media->findMedia($mediaID, $scope);
            if ($media === null || !$this->allowed($media, 'view') || !empty($media['remote_url'])) return $this->error('media_not_found');
            if ($this->access !== null && !$this->access->allows((string)$media['source'], 'visibility')) return $this->error('media_forbidden');
            $previous = (string)($media['visibility'] ?? 'public');
            if ($previous === $visibility) return ['status' => 'success', 'code' => 'media_updated'];
            $directory = $this->storage->uploadDirectory((string)$media['source'], date('Y'), date('m'), $scope, $visibility);
            $variants = $this->decodeJson((string)($media['variants_json'] ?? '{}'));
            $targets = [];
            $relocated = [];
            foreach (['original' => $media['path']] + $variants as $key => $oldPath) {
                if (isset($relocated[$oldPath])) {
                    $targets[$key] = $relocated[$oldPath];
                    continue;
                }
                $extension = pathinfo((string)$oldPath, PATHINFO_EXTENSION);
                $filename = $this->storage->uniqueName($directory['absolute'], bin2hex(random_bytes(16)), $extension);
                $newPath = $directory['relative'] . '/' . $filename;
                $from = $this->storage->absolute((string)$oldPath, $previous);
                $to = $this->storage->absolute($newPath, $visibility);
                if (!rename($from, $to)) throw new \RuntimeException('No se pudo trasladar el archivo.');
                $moved[] = [$from, $to];
                $targets[$key] = $newPath;
                $relocated[$oldPath] = $newPath;
            }
            $path = $targets['original'];
            unset($targets['original']);
            $this->media->updateMedia($mediaID, $scope, ['visibility' => $visibility, 'path' => $path, 'name' => basename($path), 'variants_json' => $this->encodeJson($targets)]);
            return ['status' => 'success', 'code' => 'media_updated'];
        } catch (Exception $exception) {
            foreach (array_reverse($moved) as [$from, $to]) {
                if (!rename($to, $from)) error_log('[GFrame Media] No se pudo revertir un traslado multimedia.');
            }
            return $this->failure($exception, 'media_update_failed');
        }
    }
}
