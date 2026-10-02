<?php

namespace GFrame\Media;

use Exception;
use FilesystemIterator;
use GFrame\Media\Contracts\MediaRepository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class MediaSyncService
{
    public function __construct(
        private readonly MediaRepository $media,
        private readonly MediaStorage $storage,
        private readonly MediaProcessor $processor = new MediaProcessor()
    ) {
    }

    public function synchronize(?MediaScope $scope = null): array
    {
        $scope ??= MediaScope::global();
        try {
            $rootRelative = implode('/', array_merge(['uploads'], $scope->pathSegments()));
            $rootAbsolute = $this->storage->absolute($rootRelative);
            if (!is_dir($rootAbsolute)) {
                return ['status' => 'success', 'code' => 'media_synchronized', 'data' => ['added' => 0, 'ignored' => 0]];
            }
            $existing = array_fill_keys($this->media->paths($scope), true);
            $variantNames = array_unique(array_merge(['small', 'optimized', 'xsmall', 'medium', 'preview', 'large'], $this->processor->getVariantKeys()));
            $variantPattern = '/-(' . implode('|', array_map(static fn(string $key): string => preg_quote($key, '/'), $variantNames)) . ')\.[a-z0-9]+$/i';
            $added = 0;
            $ignored = 0;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootAbsolute, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
                $relative = $this->relativePath($rootAbsolute, $rootRelative, $file->getPathname());
                $name = $file->getFilename();
                if (isset($existing[$relative]) || preg_match($variantPattern, $name)) {
                    $ignored++;
                    continue;
                }
                $extension = $this->storage->normalizeExtension((string)pathinfo($name, PATHINFO_EXTENSION));
                $classification = $this->processor->classify($this->processor->detectMime($file->getPathname()), $name);
                $kind = (string)($classification['type'] ?? 'unknown');
                $allowed = $this->processor->getAllowedByKindMap()[$kind] ?? null;
                if ($allowed === null || !in_array($extension, (array)$allowed['exts'], true)) {
                    $ignored++;
                    continue;
                }
                $validation = $kind === 'images'
                    ? $this->processor->validateImageUpload($file->getPathname(), (array)$allowed['mimes'])
                    : $this->processor->validateNonImageMime(
                        $file->getPathname(), $kind, (string)($classification['mime'] ?? ''), $extension, (array)$allowed['mimes']
                    );
                if (empty($validation['ok'])) {
                    $ignored++;
                    continue;
                }
                $parts = explode('/', $relative);
                $sourceIndex = 1 + count($scope->pathSegments());
                $this->media->createMedia([
                    'scope_type' => $scope->type(), 'scope_id' => $scope->id(),
                    'source' => (string)($parts[$sourceIndex] ?? 'library'), 'kind' => $kind,
                    'name' => $name, 'original_name' => $name, 'path' => $relative,
                    'mime_type' => (string)($validation['mime'] ?? $classification['mime'] ?: 'application/octet-stream'),
                    'size_bytes' => (int)$file->getSize(), 'alt_text' => '',
                    'metadata_json' => '{}', 'variants_json' => '{}', 'status' => 'ready',
                ]);
                $existing[$relative] = true;
                $added++;
            }
            return ['status' => 'success', 'code' => 'media_synchronized', 'data' => ['added' => $added, 'ignored' => $ignored]];
        } catch (Exception $exception) {
            error_log('[GFrame Media Sync] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'media_sync_failed'];
        }
    }

    private function relativePath(string $rootAbsolute, string $rootRelative, string $absolute): string
    {
        $suffix = ltrim(substr(str_replace('\\', '/', $absolute), strlen(rtrim(str_replace('\\', '/', $rootAbsolute), '/'))), '/');
        return rtrim($rootRelative, '/') . '/' . $suffix;
    }
}
