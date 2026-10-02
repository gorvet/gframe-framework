<?php

namespace GFrame\Notifications\Campaigns;

final class CampaignPlaceholders
{
    public static function validActionURL(string $value): bool
    {
        $sample = self::render($value, ['site_url' => 'https://example.test', 'dashboard_url' => 'https://example.test/admin', 'notifications_url' => 'https://example.test/notifications']);
        return $value === '' || preg_match('~^https?://[^\s]+$|^/(?!/)[^\s]*$~i', $sample) === 1;
    }

    public static function actionURL(string $value, array $context): string
    {
        $url = self::render($value, $context);
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) $url = rtrim((string)($context['site_url'] ?? ''), '/') . $url;
        return preg_match('~^https?://[^\s]+$|^/(?!/)[^\s]*$~i', $url) === 1 ? $url : '';
    }

    public static function render(string $value, array $context): string
    {
        if ($value === '') return '';
        return strtr($value, [
            '{{site_url}}' => (string)($context['site_url'] ?? ''),
            '{{dashboard_url}}' => (string)($context['dashboard_url'] ?? ''),
            '{{notifications_url}}' => (string)($context['notifications_url'] ?? ''),
            '{{user_id}}' => (string)($context['user_id'] ?? ''),
            '{{user_name}}' => (string)($context['user_name'] ?? ''),
            '{{user_email}}' => (string)($context['user_email'] ?? ''),
            '{{user_role}}' => (string)($context['user_role'] ?? ''),
            '{{user_status}}' => (string)($context['user_status'] ?? ''),
        ]);
    }
}
