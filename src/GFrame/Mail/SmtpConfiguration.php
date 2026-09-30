<?php

namespace GFrame\Mail;

use GFrame\Config\Environment;

final class SmtpConfiguration
{
    public function resolve(): array
    {
        return [
            'host' => trim((string)Environment::get('MAIL_HOST', '')),
            'port' => max(1, Environment::int('MAIL_PORT', 465)),
            'username' => trim((string)Environment::get('MAIL_USERNAME', '')),
            'password' => (string)Environment::get('MAIL_PASSWORD', ''),
            'encryption' => trim((string)Environment::get('MAIL_ENCRYPTION', 'ssl')),
            'from' => trim((string)Environment::get('MAIL_FROM_ADDRESS', '')),
            'from_name' => trim((string)Environment::get('MAIL_FROM_NAME', 'GFrame')),
        ];
    }

    public function validate(array $configuration): array
    {
        if (trim((string)($configuration['host'] ?? '')) === '') {
            return ['status' => 'error', 'code' => 'mail_host_not_configured'];
        }
        if (!filter_var((string)($configuration['from'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'code' => 'mail_sender_not_configured'];
        }
        return ['status' => 'success', 'code' => 'mail_configuration_valid'];
    }
}
