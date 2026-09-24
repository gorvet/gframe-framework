<?php
// Meta.php

class Meta {
    protected $routeParams = [];
    protected $metaTags = [];
    protected $cssLinks = [];
    protected $jsScripts = [];
    protected $jsHScripts = [];
    protected $credits = '';
    protected $schema = [];
    private static $instance;

    public function __construct() {
        $this->reset();
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function reset(): void {
        $this->routeParams = [];
        $this->metaTags = [];
        $this->cssLinks = [];
        $this->jsScripts = [];
        $this->jsHScripts = [];
        $this->credits = '';
        $this->schema = [];

        $this->initializeConfig();
    }

    public function initializeConfig() {
        $defaultRobots = (defined('SEO_ALLOW_INDEXING') && SEO_ALLOW_INDEXING)
            ? 'index,follow'
            : 'noindex,nofollow,noarchive';

        $this->setMetaTags([
            'robots' => $defaultRobots,
        ]);

        $configPath = realpath(ABSPATH . 'config/meta/global.meta.php');
        if ($configPath !== false && file_exists($configPath)) {
            $globalMeta = require $configPath;
            if (is_array($globalMeta)) {
                $this->applyMetaConfig($globalMeta);
            }
        }
    }

    public function applyMetaConfig(array $metaConfig): void {
        if (!empty($metaConfig['metaTags']) && is_array($metaConfig['metaTags'])) {
            $this->setMetaTags($metaConfig['metaTags']);
        }

        if (!empty($metaConfig['css']) && is_array($metaConfig['css'])) {
            $this->setCssLinks($metaConfig['css']);
        }

        if (!empty($metaConfig['js']) && is_array($metaConfig['js'])) {
            $this->setJsScripts($metaConfig['js']);
        }

        if (!empty($metaConfig['hjs']) && is_array($metaConfig['hjs'])) {
            $this->setHeaderJsScripts($metaConfig['hjs']);
        }

        if (array_key_exists('credits', $metaConfig)) {
            $this->setFooterCredits((string)$metaConfig['credits']);
        }

        if (!empty($metaConfig['schema']) && is_array($metaConfig['schema'])) {
            $this->setSchema($metaConfig['schema']);
        }
    }

    public function setMetaTags($metaTags) {
        $this->metaTags = array_merge($this->metaTags, $metaTags);
    }

    public function setCssLinks($cssLinks) {
        $this->cssLinks = array_values(array_unique(array_merge($this->cssLinks, $cssLinks)));
    }

    public function setJsScripts($jsScripts) {
        $this->jsScripts = array_values(array_unique(array_merge($this->jsScripts, $jsScripts)));
    }

    public function setHeaderJsScripts($jsHScripts) {
        $this->jsHScripts = array_values(array_unique(array_merge($this->jsHScripts, $jsHScripts)));
    }

    public function setFooterCredits($credits) {
        $this->credits = $credits;
    }

    public function setSchema(array $schema) {
        $this->schema = array_replace_recursive($this->schema, $schema);
    }

    public function setRouteParams(array $routeParams) {
        $this->routeParams = $routeParams;
    }

    public function getMetaTag($tagName) {
        return htmlspecialchars(isset($this->metaTags[$tagName]) ? $this->metaTags[$tagName] : '', ENT_QUOTES, 'UTF-8');
    }

    public function getCssLinks() {
        return $this->cssLinks;
    }

    public function getJsScripts() {
        return $this->jsScripts;
    }

    public function getHeaderJsScripts() {
        return $this->jsHScripts;
    }

    public function getFooterCredits() {
        return $this->credits;
    }

    public function renderSchema(): string {
        $schemaComposer = new SchemaComposer();
        $finalSchema = $schemaComposer->compose($this->schema, $this->metaTags, $this->routeParams);

        $jsonLD = new JsonLD();
        $json = $jsonLD->renderSchema($finalSchema, $this->metaTags, $this->routeParams);
        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
