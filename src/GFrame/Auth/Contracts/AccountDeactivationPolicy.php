<?php

namespace GFrame\Auth\Contracts;

interface AccountDeactivationPolicy
{
    public function canDeactivateAccount(array $account): bool;
}
