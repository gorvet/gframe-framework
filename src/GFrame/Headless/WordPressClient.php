<?php

namespace GFrame\Headless;

use Exception;
use GFrame\Config\Environment;

final class WordPressClient
{
    public const CONTRACT_VERSION = '2.0';
    public const API_NAMESPACE = 'bridgeframe/v2';

    private $requester;

    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $token = '',
        ?callable $requester = null
    ) {
        $this->requester = $requester ?? static fn(array $args): array => \HttpClient::request($args);
    }

    public static function fromEnvironment(?callable $requester = null): self
    {
        return new self(
            (string)Environment::get('WORDPRESS_HEADLESS_URL', ''),
            (string)Environment::get('WORDPRESS_HEADLESS_TOKEN', ''),
            $requester
        );
    }

    public function content(string $slug, string $type = 'post', bool $private = false, array $options = []): array
    {
        $slug = trim($slug);
        if ($slug === '') return ['status' => 'error', 'code' => 'invalid_slug'];
        return $this->get('html', array_merge($options, [
            'slug' => $slug, 'type' => trim($type) ?: 'post', 'private' => $private ? 'true' : 'false',
        ]), 'wordpress_content_loaded');
    }

    public function contentById(int $id, string $type = 'post', bool $private = false, array $options = []): array
    {
        if ($id <= 0) return ['status' => 'error', 'code' => 'invalid_content_id'];
        return $this->get('html', array_merge($options, [
            'id' => $id, 'type' => trim($type) ?: 'post', 'private' => $private ? 'true' : 'false',
        ]), 'wordpress_content_loaded');
    }

    public function contents(array $filters = []): array
    {
        return $this->get('list', $filters, 'wordpress_content_listed');
    }

    public function terms(string $taxonomy = 'category', bool $withTotal = true, array $options = []): array
    {
        $taxonomy = trim($taxonomy);
        if ($taxonomy === '') return ['status' => 'error', 'code' => 'invalid_taxonomy'];
        return $this->get('terms', array_merge($options, [
            'taxonomy' => $taxonomy, 'with_total' => $withTotal ? 'true' : 'false',
        ]), 'wordpress_terms_loaded');
    }

    public function menu(array $filters): array
    {
        if (empty($filters['location']) && empty($filters['slug']) && empty($filters['id'])) {
            return ['status' => 'error', 'code' => 'invalid_menu_reference'];
        }
        return $this->get('menu', $filters, 'wordpress_menu_loaded');
    }

    public function schema(): array
    {
        return $this->get('schema', [], 'wordpress_schema_loaded');
    }

    private function get(string $endpoint, array $query, string $successCode): array
    {
        $baseUrl = trim($this->baseUrl);
        if ($baseUrl === '') return ['status' => 'error', 'code' => 'wordpress_url_not_configured'];
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) return ['status' => 'error', 'code' => 'invalid_wordpress_url'];
        if (strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME)) !== 'https') return ['status' => 'error', 'code' => 'insecure_wordpress_url'];
        if (trim($this->token) === '') return ['status' => 'error', 'code' => 'wordpress_token_not_configured'];

        try {
            $response = ($this->requester)([
                'url' => rtrim($baseUrl, '/') . '/wp-json/' . self::API_NAMESPACE . '/' . ltrim($endpoint, '/'),
                'method' => 'GET',
                'query' => $this->normalizeQuery($query),
                'headers' => [
                    'Authorization: Bearer ' . $this->token,
                    'Accept: application/json',
                    'X-BridgeFrame-Contract: ' . self::CONTRACT_VERSION,
                ],
                'verify_peer' => true, 'verify_host' => true, 'timeout' => 30, 'max_redirects' => 3,
            ]);
        } catch (Exception $exception) {
            error_log('[GFrame WordPress] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'wordpress_connection_failed'];
        }

        $httpStatus = (int)($response['status'] ?? 0);
        $envelope = $this->normalizeData($response['json'] ?? null);
        if (empty($response['ok'])) {
            $code = match ($httpStatus) {
                400 => 'wordpress_invalid_request',
                401, 403 => 'wordpress_unauthorized',
                404 => 'wordpress_not_found',
                429 => 'wordpress_rate_limited',
                default => $httpStatus >= 500 ? 'wordpress_unavailable' : 'wordpress_request_failed',
            };
            if (!empty($response['error'])) error_log('[GFrame WordPress] ' . (string)$response['error']);
            if (is_array($envelope) && ($envelope['status'] ?? '') === 'error' && is_string($envelope['code'] ?? null)) {
                $code = $this->normalizeRemoteErrorCode((string)$envelope['code'], $code);
            }
            return ['status' => 'error', 'code' => $code, 'meta' => ['http_status' => $httpStatus]];
        }
        if (!$this->validEnvelope($envelope)) {
            return ['status' => 'error', 'code' => 'wordpress_contract_mismatch', 'meta' => ['http_status' => $httpStatus]];
        }
        $remoteMeta = (array)($envelope['meta'] ?? []);
        if ((string)($remoteMeta['contract_version'] ?? '') !== self::CONTRACT_VERSION) {
            return ['status' => 'error', 'code' => 'wordpress_contract_mismatch', 'meta' => ['http_status' => $httpStatus]];
        }
        return [
            'status' => 'success',
            'code' => $successCode,
            'data' => (array)$envelope['data'],
            'meta' => $remoteMeta + ['http_status' => $httpStatus],
        ];
    }

    private function normalizeQuery(array $query): array
    {
        $allowed = [];
        foreach ($query as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/i', $key) !== 1) continue;
            if (is_bool($value)) $value = $value ? 'true' : 'false';
            if (is_scalar($value) || $value === null) $allowed[$key] = $value;
        }
        return $allowed;
    }

    private function normalizeData(mixed $data): ?array
    {
        if (is_object($data)) $data = (array)$data;
        if (!is_array($data)) return null;
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) $data[$key] = $this->normalizeData($value);
        }
        return $data;
    }

    private function validEnvelope(?array $envelope): bool
    {
        return is_array($envelope)
            && ($envelope['status'] ?? '') === 'success'
            && is_string($envelope['code'] ?? null)
            && is_array($envelope['data'] ?? null)
            && is_array($envelope['meta'] ?? null);
    }

    private function normalizeRemoteErrorCode(string $remote, string $fallback): string
    {
        return match ($remote) {
            'invalid_token', 'insufficient_scope' => 'wordpress_unauthorized',
            'content_not_found', 'term_not_found', 'menu_not_found' => 'wordpress_not_found',
            'invalid_request', 'invalid_content_type', 'invalid_taxonomy' => 'wordpress_invalid_request',
            'rate_limited' => 'wordpress_rate_limited',
            default => $fallback,
        };
    }
}
