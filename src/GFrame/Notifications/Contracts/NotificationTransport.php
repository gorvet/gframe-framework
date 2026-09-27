<?php

namespace GFrame\Notifications\Contracts;

interface NotificationTransport
{
    /** @param array<string, mixed> $notification */
    public function send(array $notification): void;
}
