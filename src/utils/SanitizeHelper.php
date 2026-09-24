<?php

/**
 * Helper estatico para sanitizacion basica de texto.
 */
final class SanitizeHelper
{
    private function __construct()
    {
    }

    /**
     * Elimina espacios de cierre y etiquetas HTML.
     *
     * @param mixed $value Valor a sanitizar.
     * @return string Texto saneado.
     */
    public static function sanitize($value): string
    {
        $text = rtrim((string)$value);
        return strip_tags($text);
    }
}


