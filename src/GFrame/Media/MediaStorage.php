<?php

namespace GFrame\Media;

use RuntimeException;

final class MediaStorage
{
    public function __construct(private readonly string $projectRoot)
    {
        if (!is_dir($projectRoot)) {
            throw new RuntimeException('La raíz de almacenamiento multimedia no existe.');
        }
    }

    public function uploadDirectory(
        string $source,
        string $year,
        string $month,
        ?MediaScope $scope = null
    ): array
    {
        $scope ??= MediaScope::global();
        $source = $this->segment($source, 'media');
        $year = preg_match('/^\d{4}$/', $year) === 1 ? $year : date('Y');
        $month = preg_match('/^(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('m');
        $parts = ['uploads'];
        array_push($parts, ...$scope->pathSegments());
        array_push($parts, $source, $year, $month);

        $relative = implode('/', $parts);
        $absolute = $this->absolute($relative);
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

    public function absolute(string $relative): string
    {
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '' || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('La ruta multimedia no es válida.');
        }

        return rtrim($this->projectRoot, '\\/') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    public function delete(string $relative): void
    {
        $absolute = $this->absolute($relative);
        if (is_file($absolute) && !unlink($absolute)) {
            throw new RuntimeException('No se pudo eliminar el archivo multimedia.');
        }
    }

    private function segment(string $value, string $fallback): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?: '';
        return trim($value, '-') !== '' ? trim($value, '-') : $fallback;
    }
}
