<?php

/**
 * Helper estatico para utilidades de paths/URLs de media.
 */
final class MediaPathHelper
{
    private function __construct()
    {
    }

    /**
     * Construye la URL/ruta de un thumbnail derivado desde una media original.
     *
     * Ejemplo: foto.jpg + small => foto-small.jpg
     *
     * @param string $mediaUrl URL/ruta original.
     * @param string $size Sufijo de tamano.
     * @return string URL/ruta del thumbnail.
     */
    public static function thumbnailUrl(string $mediaUrl, string $size): string
    {
        $mediaUrl = trim($mediaUrl);
        $size = trim($size);
        if ($mediaUrl === '' || $size === '') {
            return $mediaUrl;
        }

        $extension = pathinfo($mediaUrl, PATHINFO_EXTENSION);
        if ($extension === '') {
            return $mediaUrl . '-' . $size;
        }

        $suffix = '.' . $extension;
        if (str_ends_with($mediaUrl, $suffix)) {
            $base = substr($mediaUrl, 0, -strlen($suffix));
            return $base . '-' . $size . $suffix;
        }

        return $mediaUrl . '-' . $size . $suffix;
    }
}

