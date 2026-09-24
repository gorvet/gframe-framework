<?php

class Llms
{
    protected $sitemapDataProvider;

    public function __construct()
    {
        $this->sitemapDataProvider = new SitemapDataProvider();
    }

    public function index($routeParams = []): void
    {
        if (!$this->isEnabled()) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Not found\n";
            exit;
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo $this->render();
        exit;
    }

    private function isEnabled(): bool
    {
        return defined('SEO_ALLOW_INDEXING')
            && SEO_ALLOW_INDEXING
            && (!defined('SEO_ENABLE_LLMS_TXT') || SEO_ENABLE_LLMS_TXT);
    }

    private function render(): string
    {
        $siteName = $this->siteName();
        $summary = $this->siteSummary();
        $pages = $this->collectPublicPages(RouteBuilder::all());

        $lines = [
            '# ' . $siteName,
            '',
        ];

        if ($summary !== '') {
            $lines[] = '> ' . $summary;
            $lines[] = '';
        }

        $lines[] = 'This file gives language models a concise map of the public website.';
        $lines[] = '';

        if (!empty($pages)) {
            $lines[] = '## Pages';
            foreach ($pages as $page) {
                $lines[] = $this->formatLink($page['title'], $page['url'], $page['description']);
            }
            $lines[] = '';
        }

        $optional = [];
        if ($this->isSitemapEnabled()) {
            $optional[] = $this->formatLink('Sitemap', rtrim(site_url, '/') . '/sitemap.xml', 'Full XML index of public URLs.');
        }
        if ($this->isRobotsEnabled()) {
            $optional[] = $this->formatLink('Robots policy', rtrim(site_url, '/') . '/robots.txt', 'Crawler access policy for the site.');
        }

        if (!empty($optional)) {
            $lines[] = '## Optional';
            foreach ($optional as $line) {
                $lines[] = $line;
            }
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    private function collectPublicPages(array $routesByMethod): array
    {
        $items = [];
        $getRoutes = $routesByMethod['GET'] ?? [];

        foreach ($getRoutes as $uri => $route) {
            if (($route['type'] ?? 'web') !== 'web') {
                continue;
            }

            if (!$this->isPublicRoute($uri, $route)) {
                continue;
            }

            $path = trim((string)$uri, '/');
            if (strpos($path, '{') !== false) {
                $dynamicItems = $this->expandDynamicRoute($uri, $route);
                if (!empty($dynamicItems)) {
                    $items = array_merge($items, $dynamicItems);
                }
                continue;
            }

            $url = rtrim(site_url, '/') . ($path !== '' ? '/' . $path : '');
            $url = (string)preg_replace('#(?<!:)//+#', '/', $url);
            $meta = $this->loadRouteMeta($route, $url);

            $title = !empty($meta['title']) ? $meta['title'] : $this->titleFromPath($path);
            $description = !empty($meta['description']) ? $meta['description'] : '';

            $items[] = [
                'url' => $url,
                'title' => $title,
                'description' => $description,
            ];
        }

        $seen = [];
        $out = [];
        foreach ($items as $item) {
            if (isset($seen[$item['url']])) {
                continue;
            }

            $seen[$item['url']] = true;
            $out[] = $item;
        }

        return $out;
    }

    private function isPublicRoute(string $uri, array $route): bool
    {
        if (array_key_exists('include', (array)($route['context']['llms'] ?? [])) && $route['context']['llms']['include'] === false) {
            return false;
        }

        if (array_key_exists('include', (array)($route['context']['sitemap'] ?? [])) && $route['context']['sitemap']['include'] === false) {
            return false;
        }

        if (!empty($route['permission'])) {
            return false;
        }

        $middleware = $route['middleware'] ?? [];
        if (in_array('auth', $middleware, true)) {
            return false;
        }

        foreach ($middleware as $item) {
            if (strpos((string)$item, 'auth') === 0) {
                return false;
            }
        }

        $path = trim($uri, '/');
        if (in_array($path, ['sitemap.xml', 'robots.txt', 'llms.txt'], true)) {
            return false;
        }

        return $path === ''
            || !preg_match('#^(admin|dashboard|api|ajax|webhook|auth|login|logout|core|app|storage|vendor)(/|$)#i', $path);
    }

    private function expandDynamicRoute(string $uriTemplate, array $route): array
    {
        $meta = $this->loadSitemapMeta($route);
        $dyn = $meta['dynamic'] ?? null;
        if (empty($dyn) || !is_array($dyn)) {
            return [];
        }

        $paramMap = $dyn['params'] ?? [];
        $dataset = $dyn['dataset'] ?? [];
        $columns = $dyn['columns'] ?? [];
        $titleKey = trim((string)($dyn['title'] ?? ''));
        $titleFallbackKey = trim((string)($dyn['title_fallback'] ?? ''));
        $descriptionKey = trim((string)($dyn['description'] ?? ''));
        if (empty($paramMap) || empty($dataset['table']) || empty($columns)) {
            return [];
        }

        foreach (array_values($paramMap) as $column) {
            if (!in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }
        foreach ([$titleKey, $titleFallbackKey, $descriptionKey] as $column) {
            if ($column !== '' && !in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        $rows = $this->sitemapDataProvider->rows($dataset, $columns);
        if (empty($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array)$row;
            }

            $built = $uriTemplate;
            foreach ($paramMap as $param => $column) {
                if (!isset($row[$column])) {
                    continue 2;
                }
                $built = str_replace('{' . $param . '}', rawurlencode((string)$row[$column]), $built);
            }

            if (strpos($built, '{') !== false) {
                continue;
            }

            $path = trim($built, '/');
            $url = rtrim(site_url, '/') . ($path !== '' ? '/' . $path : '');
            $url = (string)preg_replace('#(?<!:)//+#', '/', $url);
            $metaTags = $this->loadRouteMeta($route, $url);
            $rowTitle = $titleKey !== '' ? trim((string)($row[$titleKey] ?? '')) : '';
            if ($rowTitle === '' && $titleFallbackKey !== '') {
                $rowTitle = trim((string)($row[$titleFallbackKey] ?? ''));
            }
            $rowDescription = $descriptionKey !== '' ? trim((string)($row[$descriptionKey] ?? '')) : '';

            $items[] = [
                'url' => $url,
                'title' => $rowTitle !== '' ? $this->cleanText($rowTitle) : (!empty($metaTags['title']) ? $metaTags['title'] : $this->titleFromPath($path)),
                'description' => $rowDescription !== '' ? $this->cleanText($rowDescription) : (!empty($metaTags['description']) ? $metaTags['description'] : ''),
            ];
        }

        return $items;
    }

    private function loadRouteMeta(array $route, string $url): array
    {
        $relative = RouteBuilder::relativePathFromController($route['controller'] ?? '');
        $view = $route['view'] ?? RouteBuilder::inferViewName($route['controller'] ?? '', $route['action'] ?? '');
        if (!$relative || !$view) {
            return [];
        }

        $metaPath = realpath(ABSPATH . "app/views/{$relative}/{$view}.meta.php");
        if (!$metaPath || !file_exists($metaPath)) {
            return [];
        }

        try {
            $routeParams = [
                'currentURL' => $url,
                'context' => $route['context'] ?? [],
                'params' => [],
            ];
            $data = [];
            $metaData = require $metaPath;
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($metaData) || empty($metaData['metaTags']) || !is_array($metaData['metaTags'])) {
            return [];
        }

        $metaTags = $metaData['metaTags'];

        return [
            'title' => $this->cleanText((string)($metaTags['title'] ?? $metaTags['ogsite_name'] ?? '')),
            'description' => $this->cleanText((string)($metaTags['description'] ?? $metaTags['ogdescription'] ?? '')),
        ];
    }

    private function loadSitemapMeta(array $route): array
    {
        $relative = RouteBuilder::relativePathFromController($route['controller'] ?? '');
        $view = $route['view'] ?? RouteBuilder::inferViewName($route['controller'] ?? '', $route['action'] ?? '');
        if (!$relative || !$view) {
            return [];
        }

        $metaPath = realpath(ABSPATH . "app/views/{$relative}/{$view}.meta.php");
        if (!$metaPath || !file_exists($metaPath)) {
            return [];
        }

        try {
            $routeParams = [
                'currentURL' => '',
                'context' => $route['context'] ?? [],
                'params' => [],
            ];
            $data = [];
            $metaData = require $metaPath;
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($metaData) || empty($metaData['sitemap']) || !is_array($metaData['sitemap'])) {
            return [];
        }

        return $metaData['sitemap'];
    }

    private function siteName(): string
    {
        if (defined('site_name') && trim((string)site_name) !== '') {
            return $this->cleanText((string)site_name);
        }

        $host = (string)(parse_url(site_url, PHP_URL_HOST) ?? '');
        return $host !== '' ? $host : 'Website';
    }

    private function siteSummary(): string
    {
        $globalMeta = $this->globalMetaTags();
        $summary = (string)($globalMeta['description'] ?? $globalMeta['ogdescription'] ?? '');

        return $this->cleanText($summary);
    }

    private function globalMetaTags(): array
    {
        $metaPath = realpath(ABSPATH . 'config/meta/global.meta.php');
        if (!$metaPath || !file_exists($metaPath)) {
            return [];
        }

        try {
            $metaData = require $metaPath;
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($metaData) && !empty($metaData['metaTags']) && is_array($metaData['metaTags'])
            ? $metaData['metaTags']
            : [];
    }

    private function titleFromPath(string $path): string
    {
        if ($path === '') {
            return $this->siteName();
        }

        $title = str_replace(['-', '_', '/'], ' ', $path);
        $title = trim((string)preg_replace('/\s+/', ' ', $title));

        return $title !== '' ? ucwords($title) : $this->siteName();
    }

    private function formatLink(string $title, string $url, string $description = ''): string
    {
        $title = $this->cleanMarkdownLinkText($title !== '' ? $title : $url);
        $description = $this->cleanText($description);
        $line = '- [' . $title . '](' . $url . ')';

        if ($description !== '') {
            $line .= ': ' . $description;
        }

        return $line;
    }

    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r", "\n"], ' ', $text);
        $text = (string)preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function cleanMarkdownLinkText(string $text): string
    {
        $text = $this->cleanText($text);
        $text = str_replace(['[', ']'], ['(', ')'], $text);

        return $text;
    }

    private function isSitemapEnabled(): bool
    {
        return defined('SEO_ALLOW_INDEXING')
            && SEO_ALLOW_INDEXING
            && (!defined('SEO_ENABLE_SITEMAP_XML') || SEO_ENABLE_SITEMAP_XML);
    }

    private function isRobotsEnabled(): bool
    {
        return defined('SEO_ALLOW_INDEXING')
            && SEO_ALLOW_INDEXING
            && (!defined('SEO_ENABLE_ROBOTS_TXT') || SEO_ENABLE_ROBOTS_TXT);
    }
}
