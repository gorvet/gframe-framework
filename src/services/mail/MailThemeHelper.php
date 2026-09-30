<?php

class MailThemeHelper
{
    private static ?array $variables = null;

    public static function params(): array
    {
        // Leer de nuevo al generar cada correo, también en workers persistentes.
        self::$variables = null;
        $primary = self::var('bs-primary', '#3C6AF3');
        $secondary = self::var('bs-secondary', '#6C757D');
        $dark = self::var('bs-dark', '#18283B');
        $white = self::var('bs-white', '#fff');
        $bodyBg = '#f6f6f6';
        $font = self::var('bs-font-sans-serif', "'Montserrat', 'sans-serif', 'helvetica', Arial, Roboto");
        $siteName = defined('site_name') ? (string)site_name : 'GFrame';
        $siteUrl = defined('site_url') ? rtrim((string)site_url, '/') : '';

        return [
            'mailSiteName' => $siteName,
            'mailSiteUrl' => $siteUrl,
            'mailLogo' => $siteUrl . '/public/img/logo.png',
            'mailBodyBg' => $bodyBg,
            'mailBodyColor' => $secondary,
            'mailPrimary' => $primary,
            'mailSecondary' => $secondary,
            'mailDark' => $dark,
            'mailWhite' => $white,
            'mailFont' => $font,
            'mailCardBodyBg' => $white,
            'mailButtonStyle' => self::style([
                'display' => 'block',
                'font-size' => '16px',
                'text-decoration' => 'none',
                'color' => $white,
                'text-align' => 'center',
                'max-width' => '250px',
                'border-radius' => '5px',
                'margin' => 'auto',
                'background' => $primary,
                'border' => 'none',
                'min-width' => '150px',
                'padding' => '10px 20px',
                'margin-bottom' => '10px',
            ]),
            'mailCardBodyStyle' => self::style([
                'padding' => '30px',
                'background' => $white,
                'border-radius' => '8px',
            ]),
            'mailH1Style' => self::style([
                'line-height' => '40px',
                'font-weight' => '700',
                'font-size' => '28px',
                'color' => $primary,
                'text-align' => 'left',
                'margin-top' => '0',
            ]),
            'mailTextStyle' => self::style([
                'line-height' => '28px',
                'font-weight' => '500',
                'font-size' => '18px',
                'text-align' => 'left',
            ]),
            'mailSmallTextStyle' => self::style([
                'font-size' => '16px',
                'text-align' => 'left',
            ]),
        ];
    }

    private static function var(string $name, string $fallback): string
    {
        $variables = self::variables();
        $value = $variables[$name] ?? $fallback;
        return self::resolveValue($value, $variables, $fallback);
    }

    private static function variables(): array
    {
        if (self::$variables !== null) {
            return self::$variables;
        }

        if (!defined('ABSPATH')) {
            self::$variables = [];
            return self::$variables;
        }

        $path = realpath(ABSPATH . 'public/css/variables.css');
        if ($path === false || !file_exists($path)) {
            self::$variables = [];
            return self::$variables;
        }

        $css = file_get_contents($path);
        if (!is_string($css)) {
            self::$variables = [];
            return self::$variables;
        }

        self::$variables = self::extractLightVariables($css);
        return self::$variables;
    }

    private static function extractLightVariables(string $css): array
    {
        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
        $variables = [];
        $light = [];
        $offset = 0;
        $length = strlen($css);
        while (preg_match('/([^{}]+)\{/', $css, $block, PREG_OFFSET_CAPTURE, $offset)) {
            $selector = trim($block[1][0]);
            $start = $block[0][1] + strlen($block[0][0]);
            $end = $start;
            $depth = 1;
            while ($end < $length && $depth > 0) {
                if ($css[$end] === '{') $depth++;
                elseif ($css[$end] === '}') $depth--;
                $end++;
            }
            $offset = $end;
            if ($depth !== 0 || str_contains(substr($css, $start, $end - $start - 1), '{')) continue;
            $selectors = array_map('trim', explode(',', $selector));
            $isBase = in_array(':root', $selectors, true) || in_array('html', $selectors, true);
            $isLight = false;
            foreach ($selectors as $candidate) {
                if (preg_match('/^(?:html|:root)\[data-bs-theme\s*=\s*[\x22\x27]?light[\x22\x27]?\]$/', $candidate)) $isLight = true;
            }
            if (!$isBase && !$isLight) continue;
            preg_match_all('/--([a-z0-9_-]+)\s*:\s*([^;]+);/i', substr($css, $start, $end - $start - 1), $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if ($isBase) $variables[$match[1]] = trim($match[2]);
                if ($isLight) $light[$match[1]] = trim($match[2]);
            }
        }
        return array_replace($variables, $light);
    }

    private static function resolveValue(string $value, array $variables, string $fallback, int $depth = 0): string
    {
        $value = trim($value);

        if ($depth > 5) {
            return $fallback;
        }

        if (preg_match('/^var\(--([a-z0-9_-]+)\)$/i', $value, $match)) {
            return self::resolveValue($variables[$match[1]] ?? $fallback, $variables, $fallback, $depth + 1);
        }

        if (stripos($value, 'color-mix(') !== false) {
            return $fallback;
        }

        return $value !== '' ? $value : $fallback;
    }

    private static function style(array $declarations): string
    {
        $style = '';
        foreach ($declarations as $property => $value) {
            $style .= $property . ':' . $value . ';';
        }
        return $style;
    }
}
