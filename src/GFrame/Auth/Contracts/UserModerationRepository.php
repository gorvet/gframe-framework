<?php

namespace GFrame\Auth\Contracts;

interface UserModerationRepository
{
    public function setAccountStatus(int $userID, string $status): void;
    public function deleteAccount(int $userID): void;
}
