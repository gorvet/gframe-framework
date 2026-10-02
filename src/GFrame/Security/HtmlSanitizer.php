<?php

namespace GFrame\Security;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'table', 'thead', 'tbody', 'tr',
        'td', 'th', 'img', 'hr',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'ol' => ['start', 'type'],
        'li' => ['value'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('La limpieza de HTML requiere la extensión DOM de PHP.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="gframe-content-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('gframe-content-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $parent->removeChild($node);
                    continue;
                }

                self::sanitizeChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            $allowedAttributes = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
            foreach (iterator_to_array($node->attributes) as $attribute) {
                if (!in_array(strtolower($attribute->name), $allowedAttributes, true)) {
                    $node->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a') {
                $href = self::safeUrl($node->getAttribute('href'), true);
                if ($href === '') {
                    $node->removeAttribute('href');
                } else {
                    $node->setAttribute('href', $href);
                    $node->setAttribute('rel', 'noopener noreferrer');
                    if ($node->getAttribute('target') !== '_blank') {
                        $node->removeAttribute('target');
                    }
                }
            }

            if ($tag === 'img') {
                $src = self::safeUrl($node->getAttribute('src'), false);
                if ($src === '') {
                    $parent->removeChild($node);
                    continue;
                }
                $node->setAttribute('src', $src);
            }

            self::sanitizeChildren($node);
        }
    }

    private static function safeUrl(string $url, bool $allowMail): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        $url = preg_replace('/[\x00-\x1F\x7F]/u', '', $url) ?? '';
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) === 1 || str_starts_with($url, '/') || str_starts_with($url, 'uploads/')) {
            return $url;
        }

        if ($allowMail && preg_match('#^(mailto:|tel:)#i', $url) === 1) {
            return $url;
        }

        return '';
    }
}
