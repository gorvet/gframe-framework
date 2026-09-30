<?php

namespace GFrame\Mail\Contracts;

interface MailSender
{
    public function sendTemplate(string $recipient, string $subject, string $template, array $variables = [], array $options = []): array;

    public function sendHtml(string $recipient, string $subject, string $html, array $options = []): array;
}
