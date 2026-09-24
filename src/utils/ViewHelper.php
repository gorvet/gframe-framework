<?php

/**
 * Helper estatico para render de fragmentos de vista.
 */
final class ViewHelper
{
    private function __construct()
    {
    }

    /**
     * Renderiza un archivo PHP y devuelve su HTML como string.
     *
     * @param string $path Ruta absoluta del fragmento.
     * @param mixed $ctx Contexto opcional disponible en el include.
     * @return string HTML renderizado o cadena vacia si no existe.
     */
    public static function fragmentRender(string $path, $ctx = null): string
    {
        if ($path === '' || !file_exists($path)) {
            return '';
        }

        ob_start();
        include $path;
        return (string)ob_get_clean();
    }
}

