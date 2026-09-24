<?php

/**
 * Helper estatico para cabeceras CORS.
 */
final class CorsHelper
{
    private function __construct()
    {
    }

    /**
     * Emite cabeceras CORS para la solicitud actual.
     *
     * @param bool $allow True para permitir el origin actual.
     * @return void
     */
    public static function sendHeaders(bool $allow = false): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if (!$origin) {
            return;
        }

        if ($allow) {
            header("Access-Control-Allow-Origin: $origin");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
            header('Access-Control-Max-Age: 600');
            return;
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
}

