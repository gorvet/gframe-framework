<?php

namespace GFrame\Content;

use GFrame\Auth\TenantContextResolver;
use PDOException;
use RuntimeException;

/** Delegate only after the project route has authenticated and authorized its business record. */
final class ContentDraftController
{
    public function __construct(private readonly ContentDraftService $drafts = new ContentDraftService()) {}

    public function handle(string $authorizedResource, string $currentBaseVersion): array
    {
        $userID = (int)($_SESSION['auth']['id'] ?? 0);
        if ($userID <= 0) return ['status' => 'error', 'code' => 'forbidden', 'message' => 'Debes iniciar sesión para autoguardar.'];
        try {
            $tenantID = (new TenantContextResolver())->active();
            if ((defined('TENANT') || defined('TENANT_TABLE')) && $tenantID === null) throw new RuntimeException('Missing active tenant.');
        } catch (RuntimeException $exception) {
            return ['status' => 'error', 'code' => 'forbidden', 'message' => 'El ámbito activo no permite esta operación.'];
        }
        $action = $_POST['action'] ?? '';
        if (!is_string($action) || !in_array($action, ['load', 'save', 'discard'], true)) return $this->invalid();
        $version = $_POST['version'] ?? null;
        if ($version === '') $version = null;
        if ($version !== null && (!is_string($version) || !preg_match('/^[a-f0-9]{32}$/D', $version))) return $this->invalid();
        $base = $_POST['base_version'] ?? '';
        if (!is_string($base) || strlen($base) > 128) return $this->invalid();
        try {
            if ($action === 'load') return $this->drafts->load($userID, $tenantID, $authorizedResource);
            if ($action === 'discard') return $this->drafts->discard($userID, $tenantID, $authorizedResource, $version, $base, $currentBaseVersion);
            $content = $_POST['content'] ?? null;
            if (!is_string($content)) return $this->invalid();
            return $this->drafts->save($userID, $tenantID, $authorizedResource, $content, $version, $base, $currentBaseVersion);
        } catch (PDOException $exception) {
            error_log('[GFrame autosave] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'autosave_unavailable', 'message' => 'No se pudo actualizar el autoguardado privado. Conserva el texto antes de salir.'];
        }
    }

    private function invalid(): array
    {
        return ['status' => 'error', 'code' => 'invalid_autosave', 'message' => 'La petición de autoguardado no es válida.'];
    }
}
