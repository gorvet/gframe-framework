<?php

if (!function_exists('guess_url')) {
    function guess_url(): string
    {
        return UrlHelper::guessUrl();
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return UrlHelper::isSsl();
    }
}

if (!function_exists('sanitize')) {
    function sanitize($value): string
    {
        return SanitizeHelper::sanitize($value);
    }
}

if (!function_exists('randomNameGen')) {
    function randomNameGen($name): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $result = (string) $name . '_';

        for ($i = 0; $i < 6; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $result;
    }
}

if (!function_exists('buildMenu')) {
    function buildMenu(array $menuItems, string $currentURL = '', string $context = 'nav'): void
    {
        MenuHelper::build($menuItems, $currentURL, $context);
    }
}

if (!function_exists('pagination')) {
    function pagination($total_pages, $page): void
    {
        PaginationHelper::render((int) $total_pages, (int) $page);
    }
}

if (!function_exists('send_cors_headers')) {
    function send_cors_headers(bool $allow = false): void
    {
        CorsHelper::sendHeaders($allow);
    }
}

if (!function_exists('markdown2html')) {
    function markdown2html($str): string
    {
        return MarkdownHelper::channelMarkdownToHtml((string) $str);
    }
}

if (!function_exists('logger')) {
    function logger($data, $filename = 'loggs'): void
    {
        LogHelper::write($data, (string) $filename);
    }
}
