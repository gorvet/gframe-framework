<?php

namespace GFrame\Content;

use InvalidArgumentException;

final class ContentDraftService
{
    public const MAX_BYTES = 1048576;

    public function __construct(private readonly ContentDraftModel $model = new ContentDraftModel()) {}

    public function load(int $userID, ?int $tenantID, string $resource): array
    {
        $row = $this->model->readDraft($this->key($userID, $tenantID, $resource));
        return ['status' => 'success', 'data' => $this->view($row)];
    }

    /** Both base revisions are required: client expectation and freshly authorized server record. */
    public function save(int $userID, ?int $tenantID, string $resource, string $content, ?string $expected, string $clientBase, string $currentBase): array
    {
        if ($clientBase === '' || strlen($clientBase) > 128 || !hash_equals($currentBase, $clientBase)) return $this->conflict();
        if (strlen($content) > self::MAX_BYTES) return ['status' => 'error', 'code' => 'autosave_too_large', 'message' => 'El texto supera el límite del autoguardado.'];
        $content = \GFrame\Security\HtmlSanitizer::sanitize($content);
        return $this->write($userID, $tenantID, $resource, $expected, $content, $currentBase, false);
    }

    public function discard(int $userID, ?int $tenantID, string $resource, ?string $expected, string $clientBase, string $currentBase): array
    {
        if ($clientBase === '' || strlen($clientBase) > 128 || !hash_equals($currentBase, $clientBase)) return $this->conflict();
        return $this->write($userID, $tenantID, $resource, $expected, '', $currentBase, true);
    }

    private function write(int $userID, ?int $tenantID, string $resource, ?string $expected, string $content, string $base, bool $discarded): array
    {
        if ($expected !== null && !preg_match('/^[a-f0-9]{32}$/D', $expected)) return $this->conflict();
        $fields = ['version' => bin2hex(random_bytes(16)), 'content' => $content, 'base_version' => $base,
            'discarded' => $discarded ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 30 * 86400)];
        if (!$this->model->compareAndSwap($this->key($userID, $tenantID, $resource), $expected, $fields)) return $this->conflict();
        return ['status' => 'success', 'data' => $this->view($fields)];
    }

    private function key(int $userID, ?int $tenantID, string $resource): string
    {
        if ($userID <= 0 || ($tenantID !== null && $tenantID <= 0) || $resource === '' || strlen($resource) > 512) throw new InvalidArgumentException('El ámbito del autoguardado no es válido.');
        return hash('sha256', json_encode([$userID, $tenantID, $resource], JSON_THROW_ON_ERROR));
    }

    private function view(?array $row): array
    {
        $available = $row && !(int)$row['discarded'] && $row['expires_at'] > gmdate('Y-m-d H:i:s');
        return ['version' => $row['version'] ?? null, 'available' => (bool)$available, 'content' => $available ? $row['content'] : '',
            'base_version' => $row['base_version'] ?? '', 'updated_at' => $row['updated_at'] ?? null];
    }

    private function conflict(): array
    {
        return ['status' => 'error', 'code' => 'autosave_conflict', 'message' => 'El contenido o su autoguardado cambió en otra petición. Recarga la página antes de continuar.'];
    }
}
