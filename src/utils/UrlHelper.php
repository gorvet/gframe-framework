<?php

/**
 * Helper estatico para utilidades de URL del framework.
 */
final class UrlHelper
{
    private function __construct()
    {
    }

    /**
     * Calcula la URL base del sitio segun el entorno HTTP actual.
     *
     * @return string URL base con slash final.
     */
    public static function guessUrl(): string
    {
        $schema = self::isSsl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string)$_SERVER['HTTP_HOST']) : '';

        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
        } else {
            $port = '';
        }

        $scriptPath = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $projectRoot = dirname($scriptPath);
        if (strpos($projectRoot, '/app/') !== false) {
            $projectRoot = substr($projectRoot, 0, (int)strpos($projectRoot, '/app/'));
        }

        $url = $schema;
        if ($host !== '') {
            $url .= $host;
            if ($port !== '') {
                $url .= ':' . $port;
            }
        } elseif (!empty($_SERVER['SERVER_ADDR'])) {
            $url .= (string)$_SERVER['SERVER_ADDR'];
        } else {
            $url .= '127.0.0.1';
        }

        if ($projectRoot !== '' && $projectRoot !== '/') {
            $url .= (substr($projectRoot, -1) === '/') ? $projectRoot : ($projectRoot . '/');
        } elseif (substr($projectRoot, -1) === '/') {
            $url .= '/';
        }

        return $url;
    }

    /**
     * Detecta si la solicitud actual se atiende por SSL.
     *
     * @return bool True cuando HTTPS esta activo.
     */
    public static function isSsl(): bool
    {
        if (isset($_SERVER['HTTPS'])) {
            if ('on' === strtolower((string)$_SERVER['HTTPS'])) {
                return true;
            }

            if ('1' === (string)$_SERVER['HTTPS']) {
                return true;
            }
        } elseif (isset($_SERVER['SERVER_PORT']) && '443' === (string)$_SERVER['SERVER_PORT']) {
            return true;
        }

        return false;
    }

    /**
     * Genera URL de asset con version por filemtime para cache-busting.
     *
     * @param string $href URL/Path del asset.
     * @param string|null $localPath Path local opcional para filemtime.
     * @return string URL del asset versionada cuando aplica.
     */
    public static function assetUrl(string $href, ?string $localPath = null): string
    {
        $href = trim($href);
        if ($href === '') {
            return $href;
        }

        if (preg_match('#^(https?:)?//#i', $href) || str_starts_with($href, 'data:')) {
            return $href;
        }

        $baseUrl = defined('site_url') ? (string)site_url : self::guessUrl();
        $url = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
        $url = (string)preg_replace('#(?<!:)//+#', '/', $url);

        $path = $localPath ?: (defined('ABSPATH') ? (string)ABSPATH . ltrim($href, '/') : ltrim($href, '/'));
        $version = @filemtime($path);
        if (!$version) {
            return $url;
        }

        $join = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $join . 'v=' . $version;
    }
}

