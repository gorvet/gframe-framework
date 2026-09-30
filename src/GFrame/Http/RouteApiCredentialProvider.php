<?php

namespace GFrame\Http;

use GFrame\Http\Contracts\ApiCredentialProvider;

final class RouteApiCredentialProvider implements ApiCredentialProvider
{
    public function authenticate(string $bearer, array $context): array
    {
        if ($bearer === '') {
            return $this->invalidToken();
        }

        foreach ($this->consumers($context) as $consumer) {
            if (hash_equals($consumer['token'], $bearer)) {
                unset($consumer['token']);
                return ['status' => 'success', 'code' => 'api_consumer_authenticated', 'data' => $consumer];
            }
        }

        return $this->invalidToken();
    }

    public function allowsPreflightOrigin(string $origin, array $context): bool
    {
        foreach ($this->consumers($context) as $consumer) {
            if ($this->originAllowed($origin, $consumer['origins'])) {
                return true;
            }
        }
        return false;
    }

    public function hasCredentials(array $context): bool
    {
        return $this->consumers($context) !== [];
    }

    public function originAllowed(string $origin, array $allowed): bool
    {
        return in_array('*', $allowed, true) || in_array($this->canonicalHost($origin), $allowed, true);
    }

    private function consumers(array $context): array
    {
        $configured = $context['api_consumers'] ?? $context['api_tokens'] ?? null;
        if ($configured === null) {
            $token = trim((string)($context['api_token'] ?? $context['token'] ?? ''));
            if ($token === '') return [];
            $configured = [[
                'token' => $token,
                'name' => (string)($context['api_consumer'] ?? 'default'),
                'tenant_id' => $context['tenant_id'] ?? null,
                'scopes' => $context['scopes'] ?? [],
                'origins' => $context['allowed_origins'] ?? $context['allowed_origin'] ?? [],
            ]];
        }

        $consumers = [];
        foreach ((array)$configured as $key => $value) {
            $entry = $this->normalizeEntry($key, $value);
            if ($entry !== null) $consumers[] = $entry;
        }
        return $consumers;
    }

    private function normalizeEntry(int|string $key, mixed $value): ?array
    {
        if (is_string($value)) {
            $entry = is_string($key)
                ? ['token' => $key, 'origins' => [$value], 'name' => $key]
                : ['token' => $value, 'origins' => [], 'name' => (string)$key];
        } elseif (is_array($value)) {
            $entry = $value;
            if (!isset($entry['token']) && is_string($key)) $entry['token'] = $key;
            if (array_is_list($value)) $entry['origins'] = $value;
        } else {
            return null;
        }

        $token = trim((string)($entry['token'] ?? ''));
        if ($token === '') return null;
        $origins = $entry['origins'] ?? $entry['allowed_origins'] ?? $entry['origin'] ?? [];
        $origins = is_array($origins) ? $origins : [$origins];

        return [
            'token' => $token,
            'name' => (string)($entry['name'] ?? $key),
            'tenant_id' => $entry['tenant_id'] ?? null,
            'scopes' => array_values(array_filter(array_map('strval', (array)($entry['scopes'] ?? [])))),
            'origins' => array_values(array_filter(array_map(fn (mixed $item): string => $this->canonicalHost((string)$item), $origins))),
        ];
    }

    private function canonicalHost(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '*') return '*';
        $host = parse_url(str_contains($value, '://') ? $value : 'https://' . $value, PHP_URL_HOST) ?: $value;
        return (string)preg_replace('/^www\./', '', strtolower($host));
    }

    private function invalidToken(): array
    {
        return ['status' => 'unauthorized', 'code' => 'invalid_token', 'message' => 'Token de acceso inválido.', 'http_code' => 401];
    }
}
