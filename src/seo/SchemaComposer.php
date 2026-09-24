<?php

class SchemaComposer {
    public function compose(array $schema, array $metaTags = [], array $routeParams = []): array {
        $composed = $schema;

        $composed = $this->applyPresetChain($composed);

        $composed = $this->normalizeLegacyBlocks($composed);

        if (empty($composed['type'])) {
            $composed['type'] = $this->inferType($composed);
        }

        if (empty($composed['lang'])) {
            $composed['lang'] = $routeParams['lang'] ?? ($metaTags['oglocale'] ?? 'es');
        }

        if (empty($composed['siteName']) && !empty($metaTags['ogsite_name'])) {
            $composed['siteName'] = $metaTags['ogsite_name'];
        }

        if (empty($composed['title']) && !empty($metaTags['title'])) {
            $composed['title'] = $metaTags['title'];
        }

        if (empty($composed['description']) && !empty($metaTags['description'])) {
            $composed['description'] = $metaTags['description'];
        }

        if (empty($composed['image']) && !empty($metaTags['ogimage'])) {
            $composed['image'] = $metaTags['ogimage'];
        }

        if (empty($composed['search']['target'])) {
            $composed['search'] = $composed['search'] ?? [];
            $composed['search']['target'] = rtrim(site_url, '/') . '/buscar?q={search_term_string}';
        }

        if (($composed['type'] ?? '') === 'Article' && empty($composed['author']) && !empty($metaTags['author'])) {
            $composed['author'] = $metaTags['author'];
        }

        if (empty($composed['org']) || !is_array($composed['org'])) {
            $composed['org'] = [];
        }
        if (empty($composed['org']['logo']) && !empty($metaTags['ogimage'])) {
            $composed['org']['logo'] = $metaTags['ogimage'];
        }

        return $composed;
    }

    private function inferType(array $schema): string {
        if ($this->hasSchemaBlock($schema, 'product')) return 'Product';
        if ($this->hasSchemaBlock($schema, 'software')) return 'SoftwareApplication';
        if ($this->hasSchemaBlock($schema, 'service')) return 'Service';
        if ($this->hasSchemaBlock($schema, 'course')) return 'Course';
        if ($this->hasSchemaBlock($schema, 'event')) return 'Event';
        if ($this->hasSchemaBlock($schema, 'business')) return 'LocalBusiness';
        if ($this->hasSchemaBlock($schema, 'job')) return 'JobPosting';
        if ($this->hasSchemaBlock($schema, 'video')) return 'VideoObject';
        if ($this->hasSchemaBlock($schema, 'recipe')) return 'Recipe';
        if ($this->hasSchemaBlock($schema, 'creativeWork')) return 'CreativeWork';
        if ($this->hasSchemaBlock($schema, 'faq')) return 'WebPage';
        return 'WebPage';
    }

    private function presetDefaults(string $preset): array {
        $presets = $this->getBuiltInPresets();
        return $presets[$preset] ?? ['type' => 'WebPage'];
    }

    private function applyPresetChain(array $schema): array {
        $presetConfig = $schema['preset'] ?? ($schema['presets'] ?? null);
        unset($schema['preset'], $schema['presets']);

        if ($presetConfig === null) {
            return $schema;
        }

        $presetList = is_array($presetConfig) ? $presetConfig : [$presetConfig];
        $resolved = [];

        foreach ($presetList as $preset) {
            $name = strtolower(trim((string)$preset));
            if ($name === '') {
                continue;
            }
            $resolved = $this->mergeRecursiveDistinct($resolved, $this->presetDefaults($name));
        }

        return $this->mergeRecursiveDistinct($resolved, $schema);
    }

    private function getBuiltInPresets(): array {
        $frameworkRoot = defined('GFRAME_PATH') ? GFRAME_PATH : ABSPATH . 'core/';
        $path = realpath($frameworkRoot . 'seo/schema.presets.php');
        if ($path === false || !file_exists($path)) {
            return ['webpage' => ['type' => 'WebPage']];
        }

        $presets = require $path;
        return is_array($presets) ? $presets : ['webpage' => ['type' => 'WebPage']];
    }

    private function normalizeLegacyBlocks(array $schema): array {
        if (!empty($schema['website']) && is_array($schema['website'])) {
            $website = $schema['website'];

            if (empty($schema['siteName']) && !empty($website['name'])) {
                $schema['siteName'] = $website['name'];
            }
            if (empty($schema['description']) && !empty($website['description'])) {
                $schema['description'] = $website['description'];
            }
            if (empty($schema['lang']) && !empty($website['inLanguage'])) {
                $schema['lang'] = $website['inLanguage'];
            }
            if (empty($schema['search']['target']) && !empty($website['potentialAction']['target'])) {
                $schema['search'] = $schema['search'] ?? [];
                $schema['search']['target'] = $website['potentialAction']['target'];
            }
        }

        if (!empty($schema['webpage']) && is_array($schema['webpage'])) {
            $webpage = $schema['webpage'];

            if (empty($schema['title']) && !empty($webpage['name'])) {
                $schema['title'] = $webpage['name'];
            }
            if (empty($schema['description']) && !empty($webpage['description'])) {
                $schema['description'] = $webpage['description'];
            }
            if (empty($schema['image']) && !empty($webpage['primaryImageOfPage']['url'])) {
                $schema['image'] = $webpage['primaryImageOfPage']['url'];
            }
        }

        return $schema;
    }

    private function mergeRecursiveDistinct(array $base, array $override): array {
        foreach ($override as $key => $value) {
            if (!array_key_exists($key, $base)) {
                $base[$key] = $value;
                continue;
            }

            if (is_array($base[$key]) && is_array($value) && $this->isAssoc($base[$key]) && $this->isAssoc($value)) {
                $base[$key] = $this->mergeRecursiveDistinct($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private function isAssoc(array $array): bool {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function hasSchemaBlock(array $schema, string $key): bool {
        return array_key_exists($key, $schema) && $schema[$key] !== null;
    }
}
