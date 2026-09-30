<?php

use GFrame\Media\MediaLibraryService;
use GFrame\Media\MediaModel;
use GFrame\Media\MediaProcessor;
use GFrame\Media\MediaScopeResolver;
use GFrame\Media\MediaStorage;
use GFrame\Media\MediaSyncService;

final class MediaController
{
    private MediaLibraryService $media;
    private MediaScopeResolver $scopes;
    private MediaSyncService $sync;

    public function __construct(?MediaLibraryService $media = null, ?MediaScopeResolver $scopes = null, ?MediaSyncService $sync = null)
    {
        $model = new MediaModel();
        $storage = new MediaStorage(ABSPATH . 'public');
        $processor = new MediaProcessor();
        $this->media = $media ?? new MediaLibraryService($model, $storage, $processor);
        $this->scopes = $scopes ?? new MediaScopeResolver();
        $this->sync = $sync ?? new MediaSyncService($model, $storage, $processor);
    }

    public function index(): array
    {
        $library = $this->listing();
        return ($library['status'] ?? 'error') === 'success'
            ? ['status' => 'success', 'code' => 'media_page_loaded', 'data' => ['library' => $library]]
            : $library;
    }

    public function list(): array
    {
        $response = $this->listing();
        if (($response['status'] ?? 'error') === 'success') {
            $fragment = (string)($_REQUEST['fragment'] ?? 'library');
            if (!in_array($fragment, ['library', 'picker'], true)) {
                return ['status' => 'error', 'code' => 'invalid_media_fragment', 'message' => 'El fragmento solicitado no está disponible.'];
            }
            ob_start();
            if ($fragment === 'picker') {
                $mediaItems = (array)($response['data'] ?? []);
                include ABSPATH . 'app/views/admin/media/_mediaPickerItems.php';
            } else {
                $items = (array)($response['data'] ?? []);
                $meta = (array)($response['meta'] ?? []);
                include ABSPATH . 'app/views/admin/media/_mediaList.php';
            }
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
                    $mediaItems[] = $result['data'];
                }
            }
            $allowRemoveOne = ($_POST['allow_remove_one'] ?? '1') === '1';
            ob_start();
            include ABSPATH . 'app/views/admin/media/_mediaFieldThumbs.php';
            return ['status' => 'success', 'code' => 'media_field_loaded', 'data' => $mediaItems, 'html' => (string)ob_get_clean()];
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function upload(): array
    {
        $file = (array)($_FILES['file'] ?? []);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return ['status' => 'error', 'code' => 'invalid_upload', 'message' => 'Selecciona un archivo válido.'];
        }
        try {
            return $this->withMessage($this->media->registerLocalFile(
                (string)$file['tmp_name'], (string)($file['name'] ?? 'archivo'), (string)($_POST['source'] ?? 'library'), $this->scopes->resolve()
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
                (string)($_POST['source'] ?? 'library'), $this->scopes->resolve()
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
            return $this->withMessage($this->media->details((int)($_POST['media_id'] ?? 0), $this->scopes->resolve()));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function save(): array
    {
        try {
            return $this->withMessage($this->media->updateMetadata((int)($_POST['media_id'] ?? 0), $_POST, $this->scopes->resolve()));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    public function base64(): array
    {
        try {
            return $this->withMessage($this->media->ingestBase64(
                (string)($_POST['base64'] ?? ''), (string)($_POST['name'] ?? 'generated.png'), $this->scopes->resolve()
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

    private function listing(): array
    {
        try {
            return $this->withMessage($this->media->paginate(max(1, (int)($_REQUEST['page'] ?? 1)), 24, [
                'search' => trim((string)($_REQUEST['search'] ?? '')),
                'kind' => trim((string)($_REQUEST['kind'] ?? 'all')),
                'ym' => trim((string)($_REQUEST['ym'] ?? '')),
                'source' => trim((string)($_REQUEST['source'] ?? 'library')),
            ], $this->scopes->resolve()));
        } catch (Exception $exception) {
            return $this->scopeFailure($exception);
        }
    }

    private function withMessage(array $response): array
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

    private function scopeFailure(Exception $exception): array
    {
        error_log('[GFrame Media] ' . $exception->getMessage());
        return ['status' => 'error', 'code' => 'media_scope_invalid', 'message' => 'No se pudo determinar el ámbito de la biblioteca.'];
    }
}
