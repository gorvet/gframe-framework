<?php

/**
 * Helper estatico para utilidades de texto.
 */
final class TextHelper
{
    private function __construct()
    {
    }

    /**
     * Genera un identificador aleatorio con prefijo.
     *
     * @param string $prefix Prefijo del nombre.
     * @param int $length Longitud del sufijo aleatorio.
     * @return string Nombre generado.
     */
    public static function randomName(string $prefix, int $length = 6): string
    {
        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $max = strlen($pool) - 1;
        $result = $prefix . '_';

        for ($i = 0; $i < max(1, $length); $i++) {
            $result .= $pool[random_int(0, $max)];
        }

        return $result;
    }
}

