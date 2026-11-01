<?php

namespace GFrame\Files;

use RuntimeException;

/** Shared local filesystem storage; all workers must use the same root and flock-capable filesystem. */
final class DownloadGrantStore
{
    public function __construct(private readonly PrivateFileStorage $storage) {}

    public function create(array $grant): void
    {
        $path = $this->path((string)$grant['grant_id']);
        $stream = fopen($path, 'x+b');
        if ($stream === false) throw new RuntimeException('No se pudo crear la autorización.');
        try {
            if (!flock($stream, LOCK_EX)) throw new RuntimeException('No se pudo bloquear la autorización.');
            $this->write($stream, $grant);
        } finally { fclose($stream); }
    }

    public function update(string $grantID, callable $operation): mixed
    {
        $path = $this->path($grantID);
        if (!is_file($path)) throw new RuntimeException('La descarga no está disponible.');
        $stream = fopen($path, 'r+b');
        if ($stream === false) throw new RuntimeException('La descarga no está disponible.');
        try {
            if (!flock($stream, LOCK_EX)) throw new RuntimeException('No se pudo bloquear la autorización.');
            $grant = json_decode((string)stream_get_contents($stream), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($grant) || ($grant['grant_id'] ?? '') !== $grantID) throw new RuntimeException('Autorización inválida.');
            $result = $operation($grant);
            $this->write($stream, $grant);
            return $result;
        } finally { fclose($stream); }
    }

    private function path(string $grantID): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $grantID)) throw new RuntimeException('Autorización inválida.');
        $directory = $this->storage->absolute('.downloads');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el registro de autorizaciones.');
        }
        return $this->storage->absolute('.downloads/' . $grantID . '.json');
    }

    /** @param resource $stream */
    private function write($stream, array $grant): void
    {
        $json = json_encode($grant, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        rewind($stream);
        if (!ftruncate($stream, 0) || fwrite($stream, $json) !== strlen($json) || !fflush($stream)) {
            throw new RuntimeException('No se pudo guardar la autorización.');
        }
    }
}
