<?php

namespace GFrame\Mail\Contracts;

interface SmtpConfigurationProvider
{
    /**
     * Resolve current settings on every send; never depend on the web session.
     * @return array{host: string, port: int, username: string, password: string, encryption: string, from: string, from_name: string}
     */
    public function resolve(): array;
}
