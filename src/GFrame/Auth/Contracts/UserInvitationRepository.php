<?php

namespace GFrame\Auth\Contracts;

/** Capacidad opcional para altas administrativas sin contraseña conocida. */
interface UserInvitationRepository
{
    public function invitationEmailExists(string $email): bool;
    public function createInvitedUser(string $email, int $roleID, string $token, string $issuedAt): int;
}
