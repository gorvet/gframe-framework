<?php

namespace GFrame\Tests;

use GFrame\Install\InstallationProfileCatalog;
use GFrame\Modules\ModuleCatalog;
use PHPUnit\Framework\TestCase;

final class InstallationModuleOptionsTest extends TestCase
{
    public function testRequiredModulesNeverAppearAsOptionalChoices(): void
    {
        $profiles = InstallationProfileCatalog::frameworkDefault();
        $catalog = ModuleCatalog::frameworkDefault();
        $options = $profiles->optionalModules($catalog);
        foreach ($profiles->all() as $slug => $profile) {
            $required = array_column($catalog->resolve(array_merge(array_column($catalog->defaults(), 'name'), $profile['modules'])), 'name');
            $optional = array_merge(...array_map('array_keys', array_values($options[$slug])));
            self::assertSame([], array_values(array_intersect($required, $optional)), $slug);
            self::assertNotContains('tinymce', $optional, 'Las dependencias se instalan con el editor, no se seleccionan aparte.');
            self::assertNotContains('password-utils', $optional);
        }
    }

    public function testTopicsAndProfileCompatibilityAreResolved(): void
    {
        $options = InstallationProfileCatalog::frameworkDefault()->optionalModules(ModuleCatalog::frameworkDefault());
        self::assertArrayHasKey('Contenido y búsqueda', $options['static']);
        self::assertArrayNotHasKey('Comunicación', $options['static']);
        self::assertArrayNotHasKey('rich-text-editor', $options['static']['Contenido y búsqueda']);
        self::assertArrayHasKey('rich-text-editor', $options['managed']['Contenido y búsqueda']);
        self::assertArrayNotHasKey('cron-runner', $options['static']['Herramientas']);
        self::assertArrayHasKey('notifications', $options['managed']['Comunicación']);
        self::assertArrayNotHasKey('notifications', $options['saas']['Comunicación']);
        self::assertArrayHasKey('notification-campaigns', $options['saas']['Comunicación']);
        self::assertArrayHasKey('flatpickr', $options['managed']['Formularios']);
    }

    public function testStaticUtilitiesDoNotRequireAccountsOrDatabase(): void
    {
        $catalog = ModuleCatalog::frameworkDefault();
        foreach (['markdown', 'lexical-search', 'wordpress-headless'] as $name) {
            foreach ($catalog->resolve([$name]) as $module) {
                self::assertEmpty($module['schemas'] ?? [], $name);
                self::assertEmpty($module['requires_schema'] ?? [], $name);
                self::assertNotSame('auth-ui', $module['name']);
            }
        }
        $options = InstallationProfileCatalog::frameworkDefault()->optionalModules($catalog);
        self::assertArrayHasKey('markdown', $options['static']['Contenido y búsqueda']);
        self::assertArrayHasKey('lexical-search', $options['static']['Contenido y búsqueda']);
    }

    public function testFunctionalModulesAutomaticallyBringTheirRequiredLibraries(): void
    {
        $catalog = ModuleCatalog::frameworkDefault();
        $campaigns = array_column($catalog->resolve(['notification-campaigns']), 'name');
        foreach (['notifications', 'cron-runner', 'flatpickr', 'gf-select'] as $name) self::assertContains($name, $campaigns);
        self::assertContains('cron-runner', array_column($catalog->resolve(['notifications']), 'name'));
        self::assertContains('tinymce', array_column($catalog->resolve(['rich-text-editor']), 'name'));
        $defaults = array_column($catalog->defaults(), 'name');
        foreach (['bootstrap', 'jquery', 'sweetalert2', 'alerts', 'frontend-core', 'gframe-icons', 'gf-select', 'gf-table', 'error-pages'] as $name) self::assertContains($name, $defaults);
    }
}
