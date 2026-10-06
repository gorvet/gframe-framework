<?php

namespace GFrame\Mail;

use Exception;
use GFrame\Mail\Contracts\MailSender;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * La opción rate_limit protege formularios públicos de correo.
 * Los envíos internos de la aplicación deben omitir esa opción.
 */
final class MailService implements MailSender
{
    public function __construct(
        private readonly ?SmtpConfiguration $configuration = null,
        private readonly ?MailTemplateRegistry $templates = null,
        private readonly ?\Async $async = null,
        private readonly ?MailRateLimiter $rateLimiter = null
    ) {}

    public function sendTemplateAsync(string $recipient, string $subject, string $template, array $variables = [], array $options = []): array
    {
        if (array_key_exists('rate_limit', $options)) {
            return $this->limited($options, fn(array $clean): array => $this->sendTemplateAsync($recipient, $subject, $template, $variables, $clean));
        }
        return $this->dispatch(static function () use ($recipient, $subject, $template, $variables, $options): void {
            $result = (new MailService())->sendTemplate($recipient, $subject, $template, $variables, $options);
            if (($result['status'] ?? '') !== 'success') error_log('[GFrame Mail Async] ' . (string)($result['code'] ?? 'mail_send_failed'));
        });
    }

    public function sendHtmlAsync(string $recipient, string $subject, string $html, array $options = []): array
    {
        if (array_key_exists('rate_limit', $options)) {
            return $this->limited($options, fn(array $clean): array => $this->sendHtmlAsync($recipient, $subject, $html, $clean));
        }
        return $this->dispatch(static function () use ($recipient, $subject, $html, $options): void {
            $result = (new MailService())->sendHtml($recipient, $subject, $html, $options);
            if (($result['status'] ?? '') !== 'success') error_log('[GFrame Mail Async] ' . (string)($result['code'] ?? 'mail_send_failed'));
        });
    }

    public function sendTemplate(string $recipient, string $subject, string $template, array $variables = [], array $options = []): array
    {
        if (array_key_exists('rate_limit', $options)) {
            return $this->limited($options, fn(array $clean): array => $this->sendTemplate($recipient, $subject, $template, $variables, $clean));
        }
        try {
            $variables += ['recipient_name' => trim((string)($options['recipient_name'] ?? '')) ?: (trim((string)($variables['user_name'] ?? '')) ?: (string)strtok($recipient, '@'))];
            $html = ($this->templates ?? new MailTemplateRegistry())->render($template, $variables);
        } catch (Exception $exception) {
            error_log('[GFrame Mail] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'mail_template_failed'];
        }
        return $this->sendHtml($recipient, $subject, $html, $options);
    }

    public function sendHtml(string $recipient, string $subject, string $html, array $options = []): array
    {
        if (array_key_exists('rate_limit', $options)) {
            return $this->limited($options, fn(array $clean): array => $this->sendHtml($recipient, $subject, $html, $clean));
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'code' => 'invalid_email'];
        }
        $smtp = ($this->configuration ?? new SmtpConfiguration())->resolve();
        $validation = ($this->configuration ?? new SmtpConfiguration())->validate($smtp);
        if (($validation['status'] ?? '') !== 'success') return $validation;

        try {
            $mailer = new PHPMailer(true);
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            $mailer->isSMTP();
            $mailer->SMTPAuth = (string)$smtp['username'] !== '';
            $mailer->Host = (string)$smtp['host'];
            $mailer->Port = (int)$smtp['port'];
            $mailer->Username = (string)$smtp['username'];
            $mailer->Password = (string)$smtp['password'];
            $mailer->SMTPSecure = (string)$smtp['encryption'];
            $mailer->Timeout = max(1, (int)($options['timeout'] ?? 60));
            $mailer->setFrom((string)$smtp['from'], (string)$smtp['from_name']);
            $mailer->addAddress($recipient, (string)($options['recipient_name'] ?? ''));
            $replyTo = trim((string)($options['reply_to'] ?? ''));
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mailer->addReplyTo($replyTo, (string)($options['reply_name'] ?? ''));
            }
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $html;
            $mailer->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
            $mailer->send();
            return ['status' => 'success', 'code' => 'mail_sent'];
        } catch (Exception $exception) {
            error_log('[GFrame Mail] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'mail_send_failed'];
        }
    }

    private function limited(array $options, \Closure $send): array
    {
        $limit = $options['rate_limit'];
        unset($options['rate_limit']);
        if (!is_array($limit) || !is_string($limit['scope'] ?? null) || !is_string($limit['identity'] ?? null)) {
            return ['status' => 'error', 'code' => 'mail_rate_limit_invalid'];
        }
        return ($this->rateLimiter ?? new MailRateLimiter())->run($limit['scope'], $limit['identity'], fn(): array => $send($options));
    }

    private function dispatch(\Closure $job): array
    {
        try {
            ($this->async ?? new \Async())->create($job);
            return ['status' => 'success', 'code' => 'mail_queued'];
        } catch (Exception $exception) {
            error_log('[GFrame Mail Async] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'mail_queue_failed'];
        }
    }
}
