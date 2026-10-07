const { test } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { readFileSync } = require('node:fs');
const path = require('node:path');
let chromium;
try { ({ chromium } = require('playwright')); } catch {}

test('Biblioteca y picker mantienen IDs, filtros y paginación independientes', { skip: !chromium || !process.env.GFRAME_TEST_BROWSER }, async () => {
    const root = path.resolve(__dirname, '../..').replaceAll('\\', '/');
    const php = `require ${JSON.stringify(root + '/packages/autoload.php')}; define('site_url', 'https://example.test/');
      foreach (['library', 'picker'] as $fragment) {
        $recent = ['data' => [['media_id' => 7, 'media_url' => 'test.pdf', 'type' => 'docs', 'name' => 'Test']], 'meta' => ['page' => 1, 'total_pages' => 3], 'filters' => []];
        echo '<section id="mount-' . $fragment . '">';
        include ${JSON.stringify(root + '/resources/modules/media-library/application/app/views/media-library/_mlist.php')};
        echo '</section>';
      }`;
    const html = execFileSync(process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe', ['-r', php], { encoding: 'utf8' });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.GFRAME_TEST_BROWSER });
    try {
        const page = await browser.newPage();
        await page.setContent('<form id="tokens"></form>' + html);
        for (const file of ['jquery/public/jquery.min.js', 'frontend-core/public/utils/pagination.js', 'media-library/javascript/media-library.js']) {
            await page.addScriptTag({ content: readFileSync(root + '/resources/modules/' + file, 'utf8') });
        }
        const result = await page.evaluate(() => {
            const ids = [...document.querySelectorAll('[id]')].map(node => node.id);
            const calls = [];
            const library = new MediaLibrary({ mount: '#mount-library', tokens: '#tokens', mode: 'manage', syncUrlEnabled: false });
            const picker = new MediaLibrary({ mount: '#mount-picker', tokens: '#tokens', mode: 'picker', syncUrlEnabled: false });
            library.load = page => calls.push(['library', page]);
            picker.load = page => calls.push(['picker', page]);
            window.fetchDataForPage = page => calls.push(['global', page]);
            $('#mount-picker .next').trigger('click');
            $('#mount-library .linkeable').first().trigger('click');
            $('#mp-media-kind-filter').val('docs').trigger('change');
            $('#mp-media-search-input').val('picker query');
            const pickerPayload = picker._buildListData(2);
            const libraryPayload = library._buildListData(1);
            return { ids, calls, pickerPayload, libraryPayload, item: picker.normalizeItem($('#mp-media-7')).id };
        });
        assert.equal(new Set(result.ids).size, result.ids.length);
        assert.deepEqual(result.calls, [['picker', 2], ['library', 2], ['picker', 1]]);
        assert.match(result.pickerPayload, /fragment=picker/);
        assert.match(result.pickerPayload, /q=picker%20query/);
        assert.match(result.pickerPayload, /kind=docs/);
        assert.match(result.libraryPayload, /fragment=library/);
        assert.doesNotMatch(result.libraryPayload, /picker%20query|kind=docs/);
        assert.equal(result.item, 7);
    } finally { await browser.close(); }
});
