<?php

namespace GFrame\Files;

use RuntimeException;

/** Private roots must also be denied by the web server, or outside its document root. */
final class PrivateFileStorage
{
    private string $root;

    public function __construct(string $root)
    {
        if (is_link($root)) throw new RuntimeException('La raíz privada no admite enlaces simbólicos.');
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('No se pudo crear la raíz privada.');
        }
        $resolved = realpath($root);
        if ($resolved === false) throw new RuntimeException('La raíz privada no existe.');
        $this->root = $resolved;
    }

    public function absolute(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        $parts = explode('/', $relative);
        if ($relative === '' || preg_match('/[\x00-\x1f:]/', $relative)
            || array_intersect($parts, ['', '.', '..']) !== []) {
            throw new RuntimeException('La ruta privada no es válida.');
        }
        $path = $this->root;
        foreach ($parts as $part) {
            $path .= DIRECTORY_SEPARATOR . $part;
            if (is_link($path)) throw new RuntimeException('Los archivos privados no admiten enlaces simbólicos.');
            $resolved = realpath($path);
            if ($resolved !== false && !str_starts_with($resolved, $this->root . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('La ruta está fuera de la raíz privada.');
            }
        }
        return $path;
    }

    public function file(string $relative): string
    {
        if (str_starts_with(str_replace('\\', '/', $relative), '.')) {
            throw new RuntimeException('El archivo privado no está disponible.');
        }
        $path = $this->absolute($relative);
        if (!is_file($path) || !is_readable($path)) throw new RuntimeException('El archivo privado no está disponible.');
        return $path;
    }
}
