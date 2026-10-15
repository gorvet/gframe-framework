<?php

namespace GFrame\Cron;

interface ExecutionLock
{
    public function acquire(): bool;
    public function assertOwned(): void;
    public function release(): void;
    public function now(): int;
}
