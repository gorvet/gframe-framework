<?php

namespace GFrame\Content;

use GFrame\Auth\TenantContextResolver;
use PDOException;
use RuntimeException;

/** Delegate only after authenticating and authorizing this exact business record. */
final class ContentPresenceController
{
    public function __construct(private readonly ContentPresenceService $presence = new ContentPresenceService()) {}

    public function handle(string $authorizedResource): array
    {
        $userID = (int)($_SESSION['auth']['id'] ?? 0);
        if ($userID <= 0) return $this->forbidden();
        try {
            $tenantID = (new TenantContextResolver())->active();
            if ((defined('TENANT') || defined('TENANT_TABLE')) && $tenantID === null) return $this->forbidden();
        } catch (RuntimeException) {
            return $this->forbidden();
        }
        $token = $_POST['editor_token'] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
            return ['status' => 'error', 'code' => 'invalid_content_presence', 'message' => 'La petición de presencia no es válida.'];
        }
        try {
            return $this->presence->touch($userID, $tenantID, $authorizedResource, $token);
        } catch (PDOException $exception) {
            error_log('[GFrame content presence] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'content_presence_unavailable', 'message' => 'No se pudo comprobar si hay otras sesiones de edición.'];
        }
    }

    private function forbidden(): array
    {
        return ['status' => 'unauthorized', 'code' => 'forbidden', 'message' => 'El ámbito activo no permite esta operación.'];
    }
}
