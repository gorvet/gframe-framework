<?php

namespace GFrame\Media;

use InvalidArgumentException;

final class MediaScope
{
    private const GLOBAL = 'global';
    private const TENANT = 'tenant';
    private const USER = 'user';

    private function __construct(
        private readonly string $type,
        private readonly ?int $id
    ) {
        if (!in_array($type, [self::GLOBAL, self::TENANT, self::USER], true)) {
            throw new InvalidArgumentException('El ámbito multimedia no es válido.');
        }
        if ($type === self::GLOBAL && $id !== null) {
            throw new InvalidArgumentException('La biblioteca global no utiliza identificador.');
        }
        if ($type !== self::GLOBAL && ($id === null || $id <= 0)) {
            throw new InvalidArgumentException('El ámbito multimedia requiere un identificador válido.');
        }
    }

    public static function global(): self
    {
        return new self(self::GLOBAL, null);
    }

    public static function tenant(int $tenantID): self
    {
        return new self(self::TENANT, $tenantID);
    }

    public static function user(int $userID): self
    {
        return new self(self::USER, $userID);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /** @return list<string> */
    public function pathSegments(): array
    {
        return $this->type === self::GLOBAL ? [] : [$this->type, (string)$this->id];
    }
}
