<?php

namespace GFrame\Http\Contracts;

interface ApiCredentialProvider
{
    public function authenticate(string $bearer, array $context): array;

    public function allowsPreflightOrigin(string $origin, array $context): bool;
}
