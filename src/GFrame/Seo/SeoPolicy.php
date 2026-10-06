<?php

namespace GFrame\Seo;

/** Política común de indexación para páginas y generadores de índices. */
final class SeoPolicy
{
    public static function enabled(): bool
    {
        return !defined('SEO_ENABLED') || SEO_ENABLED;
    }

    public static function allowsIndexing(): bool
    {
        return self::enabled() && (!defined('SEO_ALLOW_INDEXING') || SEO_ALLOW_INDEXING);
    }

    public static function routeIsIndexable(array $route, string $uri = '', ?string $index = null): bool
    {
        if (($route['type'] ?? 'web') !== 'web' || !empty($route['isProtected']) || !empty($route['permission'])) return false;
        if ((int)($route['httpCode'] ?? 200) >= 400) return false;
        if (($route['context']['seo']['indexable'] ?? true) === false) return false;

        // Los contratos anteriores solo excluyen los índices que les corresponden.
        if ($index !== null && ($route['context']['sitemap']['include'] ?? true) === false) return false;
        if ($index === 'llms' && ($route['context']['llms']['include'] ?? true) === false) return false;
        foreach ($route['middleware'] ?? [] as $middleware) {
            if (str_starts_with((string)$middleware, 'auth') || $middleware === 'admin'
                || str_starts_with((string)$middleware, 'role:') || str_starts_with((string)$middleware, 'can:')) return false;
        }
        $path = trim($uri !== '' ? $uri : (string)($route['uri'] ?? $route['relativePath'] ?? ''), '/');
        if (in_array($path, ['sitemap.xml', 'robots.txt', 'llms.txt'], true)) return false;
        return $path === '' || !preg_match('#^(admin|dashboard|api|ajax|webhook|auth|login|logout|core|app|storage|packages|vendor)(/|$)#i', $path);
    }
}
