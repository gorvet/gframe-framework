<?php

namespace GFrame\Auth;

use GFrame\Config\ConfigRepository;

final class TenantContextResolver
{
    /** Reject conflicting identities instead of authorizing one tenant and using another. */
    public function resolve(array $params = [], ?array $session = null): int
    {
        $session ??= $_SESSION ?? [];
        $key = defined('TENANT') ? (string)TENANT : (string)ConfigRepository::get('tenancy.key', 'tenant_id');
        $identity = 0;
        $legacy = isset($session['tenantID']) ? ['tenant_id' => $session['tenantID']] : [];
        foreach ([$params, $_POST ?? [], $_GET ?? [], $_REQUEST ?? [], $session, (array)($session['auth'] ?? []), $legacy] as $source) {
            foreach (array_unique([$key, 'tenant_id']) as $candidate) {
                if (!array_key_exists($candidate, $source)) continue;
                $value = $source[$candidate];
                if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) return 0;
                $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false || ($identity > 0 && $identity !== $id)) return 0;
                $identity = $id;
            }
        }
        return $identity;
    }

    /** Session-owned modules must never fall back to a browser-supplied identity. */
    public function active(?array $session = null): ?int
    {
        $session ??= $_SESSION ?? [];
        $key = defined('TENANT') ? (string)TENANT : (string)ConfigRepository::get('tenancy.key', 'tenant_id');
        $hasIdentity = isset($session[$key]) || isset($session['tenant_id']) || isset($session['tenantID'])
            || isset($session['auth'][$key]) || isset($session['auth']['tenant_id']);
        if (!$hasIdentity) return null;
        $id = $this->resolve([], $session);
        if ($id <= 0) throw new \RuntimeException('El tenant solicitado no coincide con el tenant activo.');
        return $id;
    }
}
