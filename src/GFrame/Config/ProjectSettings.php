<?php

namespace GFrame\Config;

use GFrame\Config\Contracts\ProjectSettingsProvider;
use RuntimeException;

final class ProjectSettings
{
    public static function load(): void
    {
        $class = ConfigRepository::get('app.settings_provider');
        if ($class === null) return;
        try {
            if (!is_string($class) || !is_a($class, ProjectSettingsProvider::class, true)) {
                throw new RuntimeException();
            }
            $settings = (new $class())->resolve();
            self::validate($settings);
            if (isset($settings['app']['language'])) {
                $language = $settings['app']['language'];
                $languages = (array)ConfigRepository::get('app.supported_languages', ['es']);
                $settings['app']['supported_languages'] = array_values(array_unique([$language, ...$languages]));
            }
            ConfigRepository::merge($settings);
        } catch (\Throwable) {
            // Provider failures can contain database credentials or other secrets.
            throw new RuntimeException('project_settings_failed');
        }
    }

    private static function validate(array $settings): void
    {
        $allowed = [
            'app' => ['name', 'language', 'timezone'],
            'seo' => ['enabled', 'allow_indexing', 'sitemap', 'robots', 'llms'],
            'analytics' => ['enabled', 'metricool'],
        ];
        foreach ($settings as $group => $values) {
            if (!isset($allowed[$group]) || !is_array($values)) throw new RuntimeException();
            foreach ($values as $key => $value) {
                if (!in_array($key, $allowed[$group], true)) throw new RuntimeException();
                if ($group === 'app') {
                    if (!is_string($value) || trim($value) === '') throw new RuntimeException();
                    if ($key === 'timezone' && !in_array($value, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) throw new RuntimeException();
                    if ($key === 'language' && !in_array($value, (array)ConfigRepository::get('app.supported_languages', ['es']), true)) throw new RuntimeException();
                } elseif ($group === 'analytics' && $key === 'metricool') {
                    if (!is_array($value)) throw new RuntimeException();
                    foreach ($value as $option => $item) {
                        if ($option === 'enabled' && is_bool($item)) continue;
                        if ($option === 'hash' && is_string($item) && preg_match('/^[a-zA-Z0-9_-]*$/D', $item)) continue;
                        throw new RuntimeException();
                    }
                } elseif (!is_bool($value)) {
                    throw new RuntimeException();
                }
            }
        }
    }
}
