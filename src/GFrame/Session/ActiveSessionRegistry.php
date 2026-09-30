<?php

namespace GFrame\Session;

interface ActiveSessionRegistry
{
    public function register(int $userID, string $sessionID, array $authorization = []): void;

    public function unregister(int $userID, string $sessionID): void;

    public function revokeUser(int $userID, bool $block = false): int;

    public function allowUser(int $userID): void;

    public function publishRoleVersion(int $roleID, int $version): void;

    public function publishUserAuthorizationVersion(int $userID, int $version): void;

    public function updateAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion, int $authorizationVersion = 1): void;

    public function updateTenantAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion): void;
}
