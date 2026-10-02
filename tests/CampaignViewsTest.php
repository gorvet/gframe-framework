<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CampaignViewsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testListAndFormUseTheCommonAdminStructureAndSeparateNavigation(): void
    {
        define('site_url', 'https://example.test/project/');
        \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['notification-campaigns'], dirname(__DIR__));
        $directory = dirname(__DIR__) . '/resources/modules/notification-campaigns/application/app/views/notification-campaigns/';
        $data = ['data' => [], 'meta' => ['total_pages' => 1, 'page' => 1], 'can_manage' => true];
        ob_start();
        include $directory . 'index.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('pagetitle', $html);
        self::assertStringContainsString('<h1 class="me-3">Campañas</h1>', $html);
        self::assertStringContainsString('ms-0 ms-md-3 mt-3 mt-md-0', $html);
        self::assertStringContainsString('card mb-4', $html);
        self::assertStringNotContainsString('justify-content-between', $html);
        self::assertStringContainsString('/admin/notifications/campaigns/new', $html);
        self::assertStringContainsString('No hay campañas para mostrar.', $html);
        self::assertStringNotContainsString('data-campaign-form', $html);
        self::assertStringNotContainsString('pagination', $html);
        self::assertStringNotContainsString('container py-', $html);
        $data['can_manage'] = false;
        ob_start();
        include $directory . 'index.php';
        self::assertStringNotContainsString('Nueva campaña', (string)ob_get_clean());

        $data = ['data' => ['campaign' => []]];
        ob_start();
        include $directory . 'form.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('<h1>Nueva campaña</h1>', $html);
        self::assertStringContainsString('data-campaign-form', $html);
        self::assertStringContainsString('needs-validation', $html);
        self::assertStringContainsString('campaign-audience', $html);
        self::assertStringContainsString('name="user_ids[]" multiple', $html);
        self::assertStringContainsString('data-placeholder="{{user_name}}"', $html);
        self::assertStringNotContainsString('campaign-placeholder-target', $html);
        self::assertStringNotContainsString('No incluye cuentas sin verificar', $html);
        self::assertTrue(strpos($html, 'id="campaign-audience"') < strpos($html, 'id="campaign-message"'));
        self::assertTrue(strpos($html, 'id="campaign-users"') < strpos($html, 'id="campaign-message"'));
        self::assertStringContainsString('d-flex justify-content-end flex-wrap gap-2', $html);
        self::assertTrue(strpos($html, '>Cancelar</a>') < strpos($html, '>Enviar ahora</button>'));
        self::assertStringContainsString('data-label-schedule="Programar campaña"', $html);
        self::assertStringContainsString('btn btn-outline-primary', $html);
        self::assertTrue(strpos($html, 'id="campaign-recurrence"') < strpos($html, 'id="campaign-inbox"'));
        self::assertTrue(strpos($html, 'id="campaign-email"') < strpos($html, 'data-preview-audience'));
        self::assertStringNotContainsString('name="recipients"', $html);
        self::assertStringContainsString('/ajax/admin/notifications/campaigns/create', $html);
        self::assertStringNotContainsString('data-campaign-list', $html);
        $data['data']['campaign'] = ['campaign_id' => 3, 'name' => '<script>', 'title' => 'Aviso', 'message' => 'Texto'];
        ob_start();
        include $directory . 'form.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('<h1>Editar campaña</h1>', $html);
        self::assertStringContainsString('/ajax/admin/notifications/campaigns/update', $html);
        self::assertStringContainsString('name="scheduled_at"', $html);
        self::assertStringContainsString('name="recurrence"', $html);
        self::assertStringContainsString('name="audience"', $html);
        self::assertStringContainsString('data-preview-audience', $html);
        self::assertTrue(strpos($html, 'id="campaign-recurrence"') < strpos($html, 'id="campaign-inbox"'));
        self::assertStringNotContainsString('Nombre interno', $html);
        self::assertStringNotContainsString('name="recipients"', $html);

        $campaigns = [['campaign_id' => 3, 'name' => 'Ejemplo', 'title' => 'Aviso', 'status' => 'paused']];
        $canManage = true;
        $meta = ['page' => 1, 'total_pages' => 1];
        ob_start(); include $directory . '_list.php'; $html = (string)ob_get_clean();
        self::assertStringNotContainsString('pagination', $html);
        self::assertStringContainsString('/edit?id=3', $html);
        self::assertStringContainsString('btn btn-outline-secondary btn-list-actions btn-sm dropdown-toggle', $html);
        self::assertStringContainsString('data-bs-config=\'{"popperConfig":{"strategy":"fixed"}}\'', $html);
        self::assertStringContainsString('/new?source=3', $html);
        $campaigns[0]['status'] = 'completed';
        ob_start(); include $directory . '_list.php'; $completed = (string)ob_get_clean();
        self::assertStringContainsString('Reciclar campaña', $completed);
        self::assertStringNotContainsString('/edit?id=3', $completed);
        self::assertStringNotContainsString('data-campaign-action', $completed);
        $meta['total_pages'] = 2;
        ob_start(); include $directory . '_list.php'; $html = (string)ob_get_clean();
        self::assertStringContainsString('all_items_pagination', $html);

        foreach (['index', 'form'] as $view) {
            $assets = require $directory . $view . '.meta.php';
            self::assertContains('public/js/modules/notification-campaigns/campaigns.js', $assets['js']);
        }
        $formAssets = require $directory . 'form.meta.php';
        self::assertContains('public/vendors/external/flatpickr/gframe-flatpickr.css', $formAssets['css']);
        $groupAssets = require $directory . 'notification-campaigns.group.meta.php';
        $mergedScripts = array_values(array_unique(array_merge($groupAssets['js'] ?? [], $formAssets['js'])));
        self::assertSame([
            'public/vendors/external/flatpickr/flatpickr.js',
            'public/vendors/external/flatpickr/flatpickr_es.js',
            'public/vendors/internal/gfselect/gf-select.js',
            'public/js/modules/notification-campaigns/campaigns.js',
        ], $mergedScripts);
        $historyAssets = require $directory . 'automaticHistory.meta.php';
        self::assertContains('public/js/modules/notification-campaigns/campaigns.js', $historyAssets['js']);
    }
}
