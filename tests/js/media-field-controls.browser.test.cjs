const {test} = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {join} = require('node:path');

test('El campo nativo muestra controles debajo y abre el picker desde las miniaturas', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const root = join(__dirname, '../..').replaceAll('\\', '/');
  const code = `define('site_url', '/');
    $base = '${root}/resources/modules/media-library/application/app/views/media-library/';
    $result = ['fields' => '', 'single' => [], 'multiple' => []];
    foreach ([1, 2] as $id) {
      $ctx = ['media_id' => $id, 'type' => 'images', 'media_url' => 'image.svg', 'name' => 'Imagen'];
      foreach (['single' => '_sthumb.php', 'multiple' => '_gthumb.php'] as $key => $file) {
        ob_start(); include $base . $file; $result[$key][$id] = ob_get_clean();
      }
    }
    foreach ([false, true] as $multiple) {
      $mediaField = ['name' => $multiple ? 'gallery' : 'cover', 'accept' => 'images', 'multiple' => $multiple, 'value' => $multiple ? [1, 2] : 1];
      ob_start(); include $base . 'mediaField.php'; $result['fields'] .= ob_get_clean();
    }
    echo json_encode($result);`;
  const fixture = JSON.parse(execFileSync(process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe', ['-r', code], {encoding: 'utf8'}));
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  try {
    const page = await browser.newPage();
    await page.setContent('<!doctype html><html><body>' + fixture.fields + '</body></html>');
    await page.addStyleTag({path: join(root, 'resources/modules/bootstrap/public/css/bootstrap.min.css')});
    await page.addStyleTag({path: join(root, 'resources/modules/media-library/public/media-library.css')});
    await page.addScriptTag({path: join(root, 'resources/modules/jquery/public/jquery.min.js')});
    await page.evaluate(fixture => {
      window.site_url = '/';
      window.picks = [];
      window.MediaPicker = {open(options) { window.picks.push(options); return Promise.resolve(null); }};
      $.ajax = options => {
        const ids = JSON.parse(options.data.media_ids);
        const fragments = options.data.variant === 'sthumb' ? fixture.single : fixture.multiple;
        return $.Deferred().resolve({status: 'success', html: ids.map(id => fragments[id]).join(''), data: ids.map(id => ({id, media_id: id, type: 'images'}))}).promise();
      };
    }, fixture);
    await page.addScriptTag({path: join(root, 'resources/modules/media-library/javascript/media-field.js')});
    await page.waitForSelector('#media-field-cover ~ [data-ml-preview] img');
    const cover = page.locator('[data-ml-media-field]').first();
    const gallery = page.locator('[data-ml-media-field]').nth(1);
    const thumb = cover.locator('[data-ml-preview] img');
    assert.equal(await thumb.evaluate(el => getComputedStyle(el).cursor), 'pointer');
    assert.equal(await cover.locator('button[data-ml-media-pick]').textContent(), 'Elegir imagen');
    assert.equal(await cover.locator('[data-ml-media-clear]').textContent(), 'Quitar');
    assert.ok((await thumb.boundingBox()).y < (await cover.locator('button[data-ml-media-pick]').boundingBox()).y);
    await thumb.click();
    await thumb.press('Enter');
    await thumb.press(' ');
    assert.equal(await page.evaluate(() => window.picks.length), 3);
    assert.deepEqual(await page.evaluate(() => window.picks[0].selected), [1]);
    await gallery.locator('[data-ml-preview] img').first().click();
    assert.equal(await page.evaluate(() => window.picks[3].multiple), true);
    await gallery.locator('[data-ml-media-remove]').first().click();
    assert.equal(await gallery.locator('[data-ml-value]').inputValue(), '[2]');
    await gallery.locator('[data-ml-media-clear]').click();
    assert.equal(await gallery.locator('[data-ml-value]').inputValue(), '[]');
    await cover.locator('[data-ml-media-clear]').click();
    assert.equal(await cover.locator('[data-ml-preview] img').count(), 0);
    assert.equal(await cover.locator('[data-ml-media-clear]').isVisible(), false);
  } finally {
    await browser.close();
  }
});
