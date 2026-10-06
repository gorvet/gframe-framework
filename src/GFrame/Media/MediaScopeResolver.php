<?php

namespace GFrame\Media;

use GFrame\Config\ConfigRepository;
use RuntimeException;

final class MediaScopeResolver
{
    public function resolve(?array $session = null): MediaScope
    {
        $session ??= $_SESSION ?? [];
        $type = mb_strtolower(trim((string)ConfigRepository::get('media.scope', 'global')), 'UTF-8');

        if ($type === 'global') {
            return MediaScope::global();
        }
        if ($type === 'user') {
            $userID = (int)($session['auth']['id'] ?? $session['userID'] ?? 0);
            if ($userID <= 0) {
                throw new RuntimeException('No se pudo resolver el usuario de la biblioteca multimedia.');
            }
            return MediaScope::user($userID);
        }
        if ($type === 'tenant') {
            $tenantID = (new \GFrame\Auth\TenantContextResolver())->active($session) ?? 0;
            if ($tenantID <= 0) {
                throw new RuntimeException('No se pudo resolver el tenant de la biblioteca multimedia.');
            }
            return MediaScope::tenant($tenantID);
        }

        throw new RuntimeException('El ámbito multimedia configurado no es válido.');
    }
}
