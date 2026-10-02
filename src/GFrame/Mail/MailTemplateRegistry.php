<?php

namespace GFrame\Mail;

use InvalidArgumentException;

final class MailTemplateRegistry
{
    public function __construct(private readonly ?string $directory = null) {}

    public function all(): array
    {
        $templates = [];
        foreach (glob($this->directory() . DIRECTORY_SEPARATOR . '*.html') ?: [] as $path) {
            $id = pathinfo($path, PATHINFO_FILENAME);
            if (preg_match('/^[a-z0-9][a-z0-9_.-]*$/i', $id) !== 1) continue;
            $metadataPath = substr($path, 0, -5) . '.json';
            $metadata = is_file($metadataPath) ? json_decode((string)file_get_contents($metadataPath), true) : [];
            preg_match_all('/{{\s*([a-zA-Z][a-zA-Z0-9_.-]*)\s*}}/', (string)file_get_contents($path), $matches);
            $declared = (array)($metadata['variables'] ?? []);
            $templates[$id] = [
                'id' => $id,
                'name' => (string)($metadata['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $id))),
                'variables' => array_values(array_unique(array_merge($declared, $matches[1] ?? []))),
                'path' => $path,
            ];
        }
        ksort($templates);
        return array_values($templates);
    }

    public function render(string $id, array $variables): string
    {
        $template = null;
        foreach ($this->all() as $candidate) if ($candidate['id'] === $id) $template = $candidate;
        if ($template === null) throw new InvalidArgumentException('No se encontró la plantilla de correo.');
        $html = (string)file_get_contents($template['path']);
        $values = array_merge(\MailThemeHelper::params(), $variables);
        $name = trim((string)($variables['recipient_name'] ?? $variables['user_name'] ?? ''));
        $hasGreeting = preg_match('/^\s*(?:hola\b|estimad[oa]s?\b|buenos días\b|buenas tardes\b|buenas noches\b)/iu', (string)($variables['message'] ?? '')) === 1;
        $values += [
            'greeting' => $hasGreeting ? '' : ($name !== '' ? 'Hola, ' . $name . '.' : 'Hola.'),
            'greeting_display' => $hasGreeting ? 'none' : 'block',
        ];
        return preg_replace_callback(
            '/{{\s*([a-zA-Z][a-zA-Z0-9_.-]*)\s*}}/',
            static fn(array $match): string => htmlspecialchars((string)($values[$match[1]] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $html
        ) ?? $html;
    }

    private function directory(): string
    {
        $root = defined('ABSPATH') ? rtrim((string)ABSPATH, '\\/') : '';
        return rtrim($this->directory ?? ($root . DIRECTORY_SEPARATOR . 'app/views/templates/mail'), '\\/');
    }
}
