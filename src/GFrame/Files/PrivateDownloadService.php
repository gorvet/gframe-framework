<?php

namespace GFrame\Files;

use GFrame\Config\ConfigRepository;
use GFrame\Http\FileResponse;
use RuntimeException;

/** Server-side API: callers authorize issuance, revocation and internal access. No session reads. */
final class PrivateDownloadService
{
    private DownloadGrantStore $grants;

    public function __construct(private readonly PrivateFileStorage $storage, ?DownloadGrantStore $grants = null)
    {
        $this->grants = $grants ?? new DownloadGrantStore($storage);
    }

    public function issue(string $path, string $name, array $options = []): array
    {
        if (!ConfigRepository::get('files.downloads.enabled', false)) throw new RuntimeException('Los enlaces externos están desactivados.');
        $this->storage->file($path);
        $ttl = $this->integer($options['ttl_seconds'] ?? ConfigRepository::get('files.downloads.ttl_seconds', 86400));
        $limit = $this->integer($options['max_downloads'] ?? ConfigRepository::get('files.downloads.max_downloads', 0));
        $userID = $this->integer($options['user_id'] ?? 0);
        $grantID = bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));
        $grant = [
            'grant_id' => $grantID, 'secret_hash' => hash('sha256', $secret), 'path' => $path, 'name' => $name,
            'mime_type' => (new \finfo(FILEINFO_MIME_TYPE))->file($this->storage->file($path)) ?: 'application/octet-stream',
            'file_hash' => hash_file('sha256', $this->storage->file($path)),
            'created_at' => time(), 'expires_at' => $ttl > 0 ? time() + $ttl : null,
            'max_downloads' => $limit, 'downloads' => 0, 'revoked' => false,
            'require_auth' => (bool)($options['require_auth'] ?? ConfigRepository::get('files.downloads.require_auth', false)),
            'user_id' => $userID, 'context' => (array)($options['context'] ?? []),
        ];
        $this->grants->create($grant);
        $this->audit('issued', $grantID);
        return ['grant_id' => $grantID, 'token' => $grantID . '.' . $secret, 'expires_at' => $grant['expires_at']];
    }

    public function revoke(string $grantID, ?callable $authorize = null): void
    {
        $this->grants->update($grantID, static function (array &$grant) use ($authorize): void {
            if ($authorize !== null && $authorize($grant) !== true) throw new RuntimeException('Autorización inválida.');
            $grant['revoked'] = true;
        });
        $this->audit('revoked', $grantID);
    }

    /** $authorize can recheck an application's current entitlement using the stored context. */
    public function resolve(string $token, int $userID = 0, string $method = 'GET', ?string $range = null, ?callable $authorize = null): FileResponse
    {
        if (!ConfigRepository::get('files.downloads.enabled', false) || !in_array($method, ['GET', 'HEAD'], true)
            || !preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D', $token, $match)) {
            throw new RuntimeException('La descarga no está disponible.');
        }
        $grantID = $match[1];
        $response = $this->grants->update($grantID, function (array &$grant) use ($match, $userID, $method, $range, $authorize): FileResponse {
            if (!hash_equals((string)$grant['secret_hash'], hash('sha256', $match[2])) || !empty($grant['revoked'])
                || ($grant['expires_at'] !== null && (int)$grant['expires_at'] <= time())
                || ((!empty($grant['require_auth']) || (int)$grant['user_id'] > 0) && $userID <= 0)
                || ((int)$grant['user_id'] > 0 && (int)$grant['user_id'] !== $userID)
                || ((int)$grant['max_downloads'] > 0 && (int)$grant['downloads'] >= (int)$grant['max_downloads'])
                || ($authorize !== null && $authorize($grant, $userID) !== true)) {
                throw new RuntimeException('La descarga no está disponible.');
            }
            $path = $this->storage->file((string)$grant['path']);
            if (!hash_equals((string)$grant['file_hash'], (string)hash_file('sha256', $path))) throw new RuntimeException('La descarga no está disponible.');
            $response = new FileResponse($path, (string)$grant['name'], (string)$grant['mime_type']);
            $response->range($method === 'HEAD' ? null : $range);
            if ($method === 'GET' && $response->status() !== 416) $grant['downloads']++;
            return $response;
        });
        $this->audit('accessed', $grantID);
        return $response;
    }

    public function internal(string $path, string $name, bool $inline = false): FileResponse
    {
        $absolute = $this->storage->file($path);
        return new FileResponse($absolute, $name, (new \finfo(FILEINFO_MIME_TYPE))->file($absolute) ?: 'application/octet-stream', $inline);
    }

    private function integer(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) throw new RuntimeException('La política de descarga no es válida.');
        return (int)$value;
    }

    private function audit(string $event, string $grantID): void
    {
        if (ConfigRepository::get('files.downloads.audit', true)) error_log('[GFrame Download] ' . $event . ' ' . $grantID);
    }
}
