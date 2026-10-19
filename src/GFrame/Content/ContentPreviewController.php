<?php
namespace GFrame\Content;

use GFrame\Auth\TenantContextResolver;

/** Invoke only after the application authorizes this exact record for preview. */
final class ContentPreviewController
{
    public function __construct(private readonly ContentDraftService $drafts = new ContentDraftService()) {}

    public function read(string $authorizedResource, string $currentRevision, string $currentHtml): array
    {
        if (headers_sent()) throw new \LogicException('Resuelva la vista previa antes de renderizar HTML.');
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Vary: Cookie', false);
        if (session_status() !== PHP_SESSION_ACTIVE || (int)($_SESSION['auth']['id'] ?? 0) <= 0) return $this->forbidden();
        try {
            $tenantID = (new TenantContextResolver())->active();
            if ((defined('TENANT') || defined('TENANT_TABLE')) && $tenantID === null) return $this->forbidden();
        } catch (\RuntimeException) { return $this->forbidden(); }
        try {
            $draft = $this->drafts->load((int)$_SESSION['auth']['id'], $tenantID, $authorizedResource)['data'];
        } catch (\PDOException $exception) {
            error_log('[GFrame preview] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'preview_unavailable', 'message' => 'No se pudo cargar la vista previa privada.'];
        }
        $useDraft = $draft['available'] && hash_equals($currentRevision, $draft['base_version']);
        $content = $useDraft ? $draft['content'] : $currentHtml;
        $version = hash('sha256', json_encode([$currentRevision, $useDraft ? $draft['version'] : null, $content], JSON_THROW_ON_ERROR));
        return ['status' => 'success', 'data' => ['content' => $content, 'version' => $version, 'private_draft' => $useDraft]];
    }

    public function state(string $authorizedResource, string $currentRevision, string $currentHtml): array
    {
        $result = $this->read($authorizedResource, $currentRevision, $currentHtml);
        if ($result['status'] === 'success') unset($result['data']['content']);
        return $result;
    }

    private function forbidden(): array { return ['status' => 'unauthorized', 'code' => 'forbidden', 'message' => 'No tienes acceso a esta vista previa.']; }
}
