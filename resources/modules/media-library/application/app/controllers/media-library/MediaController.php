<?php

namespace GFrame\Modules\MediaLibrary\Controllers;

use Exception;
use GFrame\Modules\ModuleRuntime;
use RuntimeException;
use GFrame\Media\MediaLibraryService;
use GFrame\Media\MediaModel;
use GFrame\Media\MediaProcessor;
use GFrame\Media\MediaScopeResolver;
use GFrame\Media\MediaStorage;
use GFrame\Media\MediaSyncService;

class MediaController
{
    protected MediaLibraryService $media;
    protected MediaScopeResolver $scopes;
    protected MediaSyncService $sync;

    public function __construct(?MediaLibraryService $media = null, ?MediaScopeResolver $scopes = null, ?MediaSyncService $sync = null)
    {
        $model = new MediaModel();
        $storage = new MediaStorage(ABSPATH . 'public');
        $processor = $this->createProcessor();
        $this->media = $media ?? new MediaLibraryService($model, $storage, $processor);
        $this->scopes = $scopes ?? new MediaScopeResolver();
        $this->sync = $sync ?? new MediaSyncService($model, $storage, $processor);
    }

    public function index(): array
    {
        return $this->list();
    }

    protected function createProcessor(): MediaProcessor
    {
        return new MediaProcessor();
    }

    protected function uploader(): array
    {
        $auth = (array)($_SESSION['auth'] ?? []);
        return ['id' => (int)($auth['id'] ?? $_SESSION['userID'] ?? 0),
            'name' => trim((string)($auth['name'] ?? '')) ?: (string)($auth['email'] ?? '')];
    }

    public function list(): array
    {
        $response = $this->listing();
        if (($response['status'] ?? 'error') === 'success') {
            $fragment = (string)($_REQUEST['fragment'] ?? 'library');
            if (!in_array($fragment, ['library', 'picker'], true)) {
                return ['status' => 'error', 'code' => 'invalid_media_fragment', 'message' => 'El fragmento solicitado no está disponible.'];
            }
            $response['data'] = array_map([$this, 'viewItem'], (array)($response['data'] ?? []));
            $recent = $response;
            ob_start();
            include $this->viewPath('_mlist');
            $response['html'] = (string)ob_get_clean();
        }
        return $response;
    }

    public function field(): array
    {
        $variant = trim((string)($_POST['variant'] ?? 'sthumb'));
        if (!in_array($variant, ['sthumb', 'gthumb'], true)) {
            return ['status' => 'error', 'code' => 'invalid_media_fragment', 'message' => 'La vista previa solicitada no está disponible.'];
        }
        $raw = $_POST['media_ids'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        if (!is_array($raw) || count($raw) > 50) {
            return ['status' => 'error', 'code' => 'invalid_media_selection', 'message' => 'La selección de archivos no es válida.'];
        }
        foreach ($raw as $value) {
            if (!is_int($value) && (!is_string($value) || ctype_digit($value) === false)) {
                return ['status' => 'error', 'code' => 'invalid_media_selection', 'message' => 'La selección de archivos no es válida.'];
            }
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $raw), static fn(int $id): bool => $id > 0)));
        try {
            $scope = $this->scopes->resolve();
            $mediaItems = [];
            foreach ($ids as $id) {
                $result = $this->media->details($id, $scope);
                if (($result['status'] ?? '') === 'success') {
                    $mediaItems[] = $this->viewItem($result['data']);
                }
            }
            $allowRemoveOne = (string)($_POST['allow_remove_one'] ?? '1') === '1';
            ob_start();
            foreach ($mediaItems as $ctx) {
                include $this->viewPath('_' . $variant);
            }
            return ['status' => 'success', 'code' => 'media_field_loaded', 'data' => $mediaItems, 'html' => (string)ob_get_clean()];
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function upload(): array
    {
        if (isset($_POST['media_url'])) return $this->hotlink();
        $file = (array)($_FILES['file'] ?? []);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return ['status' => 'error', 'code' => 'invalid_upload', 'message' => 'Selecciona un archivo válido.'];
        }
        try {
            return $this->withMessage($this->media->registerLocalFile(
                (string)$file['tmp_name'], (string)($file['name'] ?? 'archivo'), (string)($_POST['source'] ?? 'library'), $this->scopes->resolve(), $this->uploader()
            ));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function hotlink(): array
    {
        try {
            return $this->withMessage($this->media->registerRemoteUrl(
                (string)($_POST['media_url'] ?? ''), (string)($_POST['name'] ?? ''),
                (string)($_POST['source'] ?? 'library'), $this->scopes->resolve(), $this->uploader()
            ));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function delete(): array
    {
        try {
            return $this->withMessage($this->media->delete((int)($_POST['media_id'] ?? 0), $this->scopes->resolve()));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function details(): array
    {
        try {
            $response = $this->withMessage($this->media->details((int)($_POST['media_id'] ?? 0), $this->scopes->resolve()));
            if (($response['status'] ?? '') === 'success') $response['data'] = $this->viewItem($response['data']);
            return $response;
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function save(): array
    {
        try {
            $scope = $this->scopes->resolve();
            $id = (int)($_POST['media_id'] ?? 0);
            $input = $_POST;
            if (!array_key_exists('original_name', $input)) {
                $existing = $this->media->details($id, $scope);
                if (($existing['status'] ?? '') !== 'success') return $this->withMessage($existing);
                $input['original_name'] = $existing['data']['original_name'];
            }
            return $this->withMessage($this->media->updateMetadata($id, $input, $scope));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function base64(): array
    {
        try {
            return $this->withMessage($this->media->ingestBase64(
                (string)($_POST['base64'] ?? ''), (string)($_POST['name'] ?? 'generated.png'), $this->scopes->resolve(), $this->uploader()
            ));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function quota(): array
    {
        try {
            return $this->withMessage($this->media->quota($this->scopes->resolve()));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function sync(): array
    {
        try {
            return $this->withMessage($this->sync->synchronize($this->scopes->resolve()));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    protected function listing(): array
    {
        try {
            return $this->withMessage($this->media->paginate(max(1, (int)($_REQUEST['page'] ?? 1)), 24, [
                'search' => trim((string)($_REQUEST['q'] ?? $_REQUEST['search'] ?? '')),
                'kind' => trim((string)($_REQUEST['kind'] ?? 'all')),
                'ym' => trim((string)($_REQUEST['ym'] ?? '')),
                'source' => trim((string)($_REQUEST['source'] ?? 'all')),
            ], $this->scopes->resolve()));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    protected function viewPath(string $name): string
    {
        return ModuleRuntime::file('views', 'media-library/' . $name . '.php', 'media-library')
            ?? throw new RuntimeException('La vista multimedia no está disponible: ' . $name);
    }

    protected function viewItem(array $item): array
    {
        $url = !empty($item['remote_url']) ? (string)$item['remote_url']
            : \UrlHelper::assetUrl('public/' . ltrim((string)($item['path'] ?? ''), '/'));
        $variants = (array)($item['variants'] ?? json_decode((string)($item['variants_json'] ?? '{}'), true) ?? []);
        $metadata = (array)($item['metadata'] ?? json_decode((string)($item['metadata_json'] ?? '{}'), true) ?? []);
        $metadata['original'] = (array)($metadata['original'] ?? []) + [
            'size_bytes' => (int)($item['size_bytes'] ?? 0),
            'width' => (int)($metadata['width'] ?? 0), 'height' => (int)($metadata['height'] ?? 0),
        ];
        $item['media_url'] = $url;
        $item['type'] = (string)($item['kind'] ?? 'docs');
        $item['ext'] = pathinfo((string)($item['name'] ?? ''), PATHINFO_EXTENSION);
        $item['meta_json'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item['uploaded_by'] = (string)($metadata['uploader']['name'] ?? '') ?: (isset($metadata['uploader']['id']) ? '#' . (int)$metadata['uploader']['id'] : '—');
        $item['uploaded_to'] = (string)($item['source'] ?? '');
        foreach (['small', 'xsmall', 'medium'] as $size) {
            $item['thumb_' . $size] = !empty($variants[$size])
                ? \UrlHelper::assetUrl('public/' . ltrim((string)$variants[$size], '/')) : $url;
        }
        return $item;
    }

    protected function withMessage(array $response): array
    {
        $messages = [
            'media_created' => 'Archivo añadido a la biblioteca.',
            'media_deleted' => 'Archivo eliminado.',
            'media_not_found' => 'El archivo no existe.',
            'extension_not_allowed' => 'La extensión del archivo no está permitida.',
            'media_not_allowed' => 'El tipo de archivo no está permitido.',
            'file_not_found' => 'No se encontró el archivo temporal.',
            'file_size_not_allowed' => 'El archivo está vacío o supera el tamaño permitido.',
            'mime_not_allowed' => 'El contenido del archivo no coincide con un tipo permitido.',
            'not_image' => 'El archivo no contiene una imagen válida.',
            'image_too_large' => 'La imagen supera las dimensiones permitidas.',
            'webp_not_supported' => 'Este servidor no permite procesar imágenes WebP.',
            'extension_mime_mismatch' => 'La extensión no coincide con el contenido del archivo.',
            'media_storage_failed' => 'No se pudo guardar el archivo.',
            'media_create_failed' => 'No se pudo registrar el archivo.',
            'media_list_failed' => 'No se pudo cargar la biblioteca multimedia.',
            'media_delete_failed' => 'No se pudo eliminar el archivo.',
            'media_updated' => 'Metadatos actualizados.',
            'media_details_failed' => 'No se pudieron cargar los detalles.',
            'media_update_failed' => 'No se pudieron actualizar los metadatos.',
            'invalid_media_data' => 'Revisa el nombre y la descripción del archivo.',
            'invalid_base64_media' => 'El contenido generado no es válido.',
            'media_temporary_failed' => 'No se pudo preparar el archivo generado.',
            'media_quota_exceeded' => 'La cuota de almacenamiento está agotada.',
            'media_quota_failed' => 'No se pudo consultar la cuota.',
            'media_sync_failed' => 'No se pudo sincronizar la biblioteca.',
            'invalid_hotlink_url' => 'Introduce una URL HTTPS válida.',
            'invalid_hotlink_host' => 'El servidor remoto no está permitido.',
            'hotlink_probe_unavailable' => 'Este servidor no puede comprobar enlaces externos.',
            'hotlink_probe_failed' => 'No se pudo comprobar el archivo remoto.',
        ];
        $code = (string)($response['code'] ?? '');
        if (!isset($response['message']) && isset($messages[$code])) {
            $response['message'] = $messages[$code];
        }
        return $response;
    }

    protected function scopeFailure(Exception $exception): array
    {
        error_log('[GFrame Media] ' . $exception->getMessage());
        return ['status' => 'error', 'code' => 'media_scope_invalid', 'message' => 'No se pudo determinar el ámbito de la biblioteca.'];
    }
}
