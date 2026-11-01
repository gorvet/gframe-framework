<?php

namespace GFrame\Media;

use RuntimeException;

final class MediaStorage
{
    public function __construct(private readonly string $projectRoot, private readonly ?string $privateRoot = null)
    {
        if (!is_dir($projectRoot)) {
            throw new RuntimeException('La raíz de almacenamiento multimedia no existe.');
        }
    }

    public function uploadDirectory(
        string $source,
        string $year,
        string $month,
        ?MediaScope $scope = null,
        string $visibility = 'public'
    ): array
    {
        $scope ??= MediaScope::global();
        $source = $this->segment($source, 'media');
        $year = preg_match('/^\d{4}$/', $year) === 1 ? $year : date('Y');
        $month = preg_match('/^(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('m');
        $this->validateVisibility($visibility);
        $parts = [$visibility === 'private' ? 'media' : 'uploads'];
        array_push($parts, ...$scope->pathSegments());
        array_push($parts, $source, $year, $month);

        $relative = implode('/', $parts);
        $absolute = $this->absolute($relative, $visibility);
        if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) {
            throw new RuntimeException('No se pudo crear el directorio multimedia.');
        }

        return ['relative' => $relative, 'absolute' => $absolute];
    }

    public function normalizeExtension(string $extension): string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: '';
        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    public function uniqueName(string $directory, string $name, string $extension): string
    {
        $name = $this->segment(pathinfo($name, PATHINFO_FILENAME), 'media');
        $extension = $this->normalizeExtension($extension);
        if ($extension === '') {
            throw new RuntimeException('La extensión multimedia no es válida.');
        }

        $candidate = $name . '.' . $extension;
        $counter = 1;
        while (is_file(rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $name . '-' . $counter++ . '.' . $extension;
        }

        return $candidate;
    }

    public function absolute(string $relative, string $visibility = 'public'): string
    {
        $this->validateVisibility($visibility);
        if ($visibility === 'private') {
            if ($this->privateRoot === null) throw new RuntimeException('No se ha configurado la raíz privada.');
            $storage = new \GFrame\Files\PrivateFileStorage($this->privateRoot);
            return $storage->absolute($relative);
        }
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '' || str_starts_with($relative, '/') || preg_match('/[\x00-\x1f:]/', $relative) || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('La ruta multimedia no es válida.');
        }

        return rtrim($this->projectRoot, '\\/') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    public function delete(string $relative, string $visibility = 'public'): void
    {
        $absolute = $this->absolute($relative, $visibility);
        if (is_file($absolute) && !unlink($absolute)) {
            throw new RuntimeException('No se pudo eliminar el archivo multimedia.');
        }
    }

    public function validateVisibility(string $visibility): void
    {
        if (!in_array($visibility, ['public', 'private'], true)) throw new RuntimeException('La visibilidad multimedia no es válida.');
    }

    public function privateFiles(): \GFrame\Files\PrivateFileStorage
    {
        if ($this->privateRoot === null) throw new RuntimeException('No se ha configurado la raíz privada.');
        return new \GFrame\Files\PrivateFileStorage($this->privateRoot);
    }

    private function segment(string $value, string $fallback): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?: '';
        return trim($value, '-') !== '' ? trim($value, '-') : $fallback;
    }
}
