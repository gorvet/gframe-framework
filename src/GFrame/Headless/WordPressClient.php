<?php

namespace GFrame\Headless;

use InvalidArgumentException;

final class WordPressClient
{
    /** @var callable(array<string, mixed>):array<string, mixed> */
    private $requester;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token = '',
        ?callable $requester = null
    ) {
        if (trim($baseUrl) === '') {
            throw new InvalidArgumentException('La URL de WordPress es obligatoria.');
        }

        $this->requester = $requester ?? static fn(array $args): array => \HttpClient::request($args);
    }

    public function content(string $slug, string $type = 'post', bool $private = false): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return ['status' => 'error', 'code' => 'invalid_slug'];
        }

        return $this->get('wp-json/bridgeframe/v1/html', [
            'slug' => $slug,
            'type' => trim($type) !== '' ? trim($type) : 'post',
            'private' => $private ? 'true' : 'false',
        ]);
    }

    public function terms(string $taxonomy = 'category', bool $withTotal = true): array
    {
        $taxonomy = trim($taxonomy);
        if ($taxonomy === '') {
            return ['status' => 'error', 'code' => 'invalid_taxonomy'];
        }

        return $this->get('wp-json/bridgeframe/v1/terms', [
            'taxonomy' => $taxonomy,
            'with_total' => $withTotal,
        ]);
    }

    /** @param array<string, mixed> $query */
    private function get(string $endpoint, array $query): array
    {
        $headers = [];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $response = ($this->requester)([
            'url' => rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/'),
            'method' => 'GET',
            'query' => $query,
            'headers' => $headers,
            'verify_peer' => true,
            'verify_host' => true,
        ]);

        if (empty($response['ok'])) {
            return [
                'status' => 'error',
                'code' => 'headless_request_failed',
                'http_status' => (int)($response['status'] ?? 0),
                'message' => (string)($response['error'] ?? ''),
            ];
        }

        $data = $response['json'] ?? null;
        if (is_object($data)) {
            $data = (array)$data;
        }

        return ['status' => 'success', 'data' => $data];
    }
}
