<?php

namespace GFrame\Files;

use RuntimeException;
use GFrame\Storage\DatabaseStateStore;

/** Grants share the database, while document bytes remain in private file storage. */
final class DownloadGrantStore
{
    public function __construct(private readonly PrivateFileStorage $storage, private readonly ?DatabaseStateStore $database = null) {}

    public function create(array $grant): void
    {
        $grantID = (string)$grant['grant_id'];
        $this->store()->create('downloads', $this->key($grantID), $grant, $grant['expires_at'] ?? null);
    }

    public function update(string $grantID, callable $operation): mixed
    {
        return $this->store()->update('downloads', $this->key($grantID), $operation);
    }

    private function key(string $grantID): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $grantID)) throw new RuntimeException('Autorización inválida.');
        return hash('sha256', $this->storage->absolute('.downloads') . ':' . $grantID);
    }

    private function store(): DatabaseStateStore
    {
        return $this->database ?? DatabaseStateStore::configured();
    }
}
