const assert = require('node:assert/strict');
const {test} = require('node:test');
const {spawn} = require('node:child_process');
const {mkdtempSync, writeFileSync, unlinkSync, rmSync, readFileSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join, resolve} = require('node:path');
const net = require('node:net');

test('Selector real: subida, atributos, orden, cancelación y respuestas antiguas', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 90000}, async () => {
  const root = mkdtempSync(join(tmpdir(), 'gframe-selector-test-'));
  const listener = net.createServer();
  await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
  const port = listener.address().port;
  await new Promise(resolve => listener.close(resolve));
  const framework = join(__dirname, '../..');
  const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
  const server = spawn(php, ['-S', `127.0.0.1:${port}`, join(framework, 'tests/fixtures/media-selector.php')], {env: {...process.env, GFRAME_MEDIA_TEST_ROOT: root}, windowsHide: true});
  let logs = '';
  server.stderr.on('data', value => logs += value.toString());
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(10000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const base = `http://127.0.0.1:${port}`;
    for (let i = 0; i < 60; i++) {
      try { if ((await fetch(base)).ok) break; } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    await page.goto(base);
    await page.waitForFunction(() => !!window.MediaPicker);
    assert.equal(await page.evaluate(() => document.getElementById('application-form').reportValidity()), true);
    const modal = page.locator('#mediaPickerModal');
    const open = async options => {
      await page.evaluate(options => {
        window.pickResult = undefined;
        window.pickerShown = false;
        $('#mediaPickerModal').one('shown.bs.modal', () => window.pickerShown = true);
        window.MediaPicker.open(options).then(result => window.pickResult = result);
      }, options);
      await page.waitForFunction(() => window.pickerShown);
      await modal.waitFor({state: 'visible'});
      await modal.locator('.m-list-card').first().waitFor();
    };
    const close = async () => {
      await modal.locator('[data-bs-dismiss="modal"]').first().click();
      await modal.waitFor({state: 'hidden'});
    };
    const ids = () => modal.locator('[data-mp-selection-id]').evaluateAll(elements => elements.map(el => Number(el.dataset.mpSelectionId)));

    // Fallo rojo previsto: el selector original no muestra ni ordena toda la composición.
    await open({multiple: true, selected: [1, 2], max: 4, kind: 'images'});
    await modal.locator('[data-mp-selection-id="2"]').waitFor();
    assert.deepEqual(await ids(), [1, 2]);
    await modal.locator('[data-mp-selection-id="2"] [data-mp-move="-1"]').press('Enter');
    await page.waitForFunction(() => document.querySelector('[data-mp-selection-id]')?.dataset.mpSelectionId === '2');
    await modal.locator('#mp-insert').click();
    await page.waitForFunction(() => window.pickResult !== undefined);
    assert.deepEqual(await page.evaluate(() => pickResult.ids), [2, 1]);
    assert.equal(await page.evaluate(() => pickResult.replaceSelection), true);
    await modal.waitFor({state: 'hidden'});

    // Vaciar mientras está pendiente el render de la composición anterior.
    await open({multiple: true, selected: [1, 2], kind: 'images'});
    await modal.locator('[data-mp-selection-id="2"]').waitFor();
    await page.route('**/ajax/admin/media/field', async route => {
      const response = await route.fetch();
      if (new URLSearchParams(route.request().postData()).get('variant') === 'selection') await new Promise(resolve => setTimeout(resolve, 500));
      await route.fulfill({response});
    });
    await modal.locator('[data-mp-remove="1"]').click();
    await modal.locator('[data-mp-remove="2"]').click();
    await page.waitForTimeout(800);
    assert.equal(await modal.locator('#mp-insert').isDisabled(), false, 'La composición vacía también debe poder confirmarse.');
    await modal.locator('#mp-insert').click();
    await modal.waitFor({state: 'hidden'});
    assert.deepEqual(await page.evaluate(() => pickResult.ids), []);
    await page.unroute('**/ajax/admin/media/field');

    // Nombre visible y alt reales, guardados mediante controlador/SQLite.
    await open({selected: [1], kind: 'images'});
    const first = modal.locator('[data-media-id="1"]');
    await first.press(' ');
    await modal.locator('#mp-original-name').waitFor({state: 'visible'});
    assert.deepEqual(await page.evaluate(() => Array.from(new FormData(document.getElementById('application-form')).keys())), ['gallery']);
    await modal.locator('#mp-original-name').fill(' <b>Portada accesible</b> ');
    await modal.locator('#mp-alt-text').fill('<em>Descripción de prueba</em>');
    await modal.locator('[data-bs-dismiss="modal"]').first().click();
    assert.equal(await modal.isVisible(), true);
    await modal.locator('[data-mp-save]').click();
    await page.waitForFunction(() => document.querySelector('[data-mp-details-status]').textContent === 'Atributos guardados.');
    const detail = await (await page.request.post(base + '/ajax/admin/media/details', {form: {media_id: '1'}})).json();
    assert.equal(detail.data.original_name, 'Portada accesible');
    assert.equal(detail.data.alt_text, 'Descripción de prueba');
    assert.equal(await modal.locator('#mp-original-name').inputValue(), 'Portada accesible');
    assert.equal(await modal.locator('#mp-alt-text').inputValue(), 'Descripción de prueba');
    writeFileSync(join(root, 'deny-save'), 'deny');
    await modal.locator('#mp-alt-text').fill('No autorizado');
    await modal.locator('[data-mp-save]').click();
    await page.waitForFunction(() => document.querySelector('[data-mp-details-status]').textContent.includes('guardar'));
    const unchanged = await (await page.request.post(base + '/ajax/admin/media/details', {form: {media_id: '1'}})).json();
    assert.equal(unchanged.data.alt_text, 'Descripción de prueba');
    unlinkSync(join(root, 'deny-save'));
    await modal.locator('[data-mp-discard]').click();
    await close();

    // Las dos subidas llegan en orden inverso; el lote conserva el orden de entrada.
    await open({multiple: true, selected: [1], max: 3, kind: 'images'});
    await page.route('**/ajax/admin/media/upload', async route => {
      const response = await route.fetch();
      if (route.request().postDataBuffer().includes(Buffer.from('tercera.png'))) await new Promise(resolve => setTimeout(resolve, 500));
      await route.fulfill({response});
    });
    await modal.locator('[data-bs-target="#mp-upload"]').click();
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64');
    await modal.locator('[data-ml-file]').setInputFiles([
      {name: 'tercera.png', mimeType: 'image/png', buffer: png},
      {name: 'cuarta.png', mimeType: 'image/png', buffer: png},
      {name: 'invalido.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('invalid')},
    ]);
    await page.waitForFunction(() => document.querySelectorAll('[data-mp-selection-id]').length === 3);
    const uploadedIDs = await ids();
    const names = await modal.locator('[data-mp-selection-id] [data-mp-detail]').allTextContents();
    assert.deepEqual(names, ['Portada accesible', 'tercera.png', 'cuarta.png']);
    assert.equal(await modal.locator('progress').count(), 3);
    assert.equal(await modal.locator('[data-ml-upload-status]').filter({hasText: 'Guardado'}).count(), 2);
    await modal.locator('#mp-insert').click();
    await modal.waitFor({state: 'hidden'});
    assert.deepEqual(await page.evaluate(() => pickResult.ids), uploadedIDs);
    await page.unroute('**/ajax/admin/media/upload');

    await open({multiple: true, selected: [1], max: 1, kind: 'images'});
    await modal.locator('[data-bs-target="#mp-upload"]').click();
    await modal.locator('[data-ml-file]').setInputFiles({name: 'limite.png', mimeType: 'image/png', buffer: png});
    await page.waitForFunction(() => document.querySelector('[data-mp-notice]').textContent.includes('máximo'));
    assert.deepEqual(await ids(), [1]);
    await close();

    await open({kind: 'images'});
    await modal.locator('[data-bs-target="#mp-upload"]').click();
    await modal.locator('[data-ml-file]').setInputFiles([{name: 'simple-a.png', mimeType: 'image/png', buffer: png}, {name: 'simple-b.png', mimeType: 'image/png', buffer: png}]);
    await page.waitForFunction(() => document.querySelector('[data-mp-file-info]').textContent.includes('simple-b'));
    await modal.locator('#mp-insert').click();
    await modal.waitFor({state: 'hidden'});
    assert.equal(await page.evaluate(() => pickResult.items[0].original_name), 'simple-b.png');

    // El registro desde URL comparte selección; se simula solo el transporte externo.
    await open({kind: 'images'});
    await page.route('**/ajax/admin/media/upload', route => route.fulfill({json: {status: 'success', data: {media_id: 2}}}));
    await modal.locator('[data-bs-target="#mp-hotlink"]').click();
    await modal.locator('[data-ml-url-input]').fill('https://example.test/imagen.png');
    await modal.locator('[data-ml-url-add]').click();
    await page.waitForFunction(() => document.querySelector('[data-mp-file-info]').textContent.includes('segunda'));
    await modal.locator('#mp-insert').click();
    await modal.waitFor({state: 'hidden'});
    assert.deepEqual(await page.evaluate(() => pickResult.ids), [2]);
    await page.unroute('**/ajax/admin/media/upload');

    // La edición de composición sustituye el append histórico solo de forma explícita.
    await page.locator('[data-ml-media-pick]').first().click();
    await modal.waitFor({state: 'visible'});
    await modal.locator('[data-mp-selection-id="2"]').waitFor();
    await modal.locator('[data-mp-remove="1"]').click();
    await modal.locator('[data-mp-selection-id="1"]').waitFor({state: 'hidden'});
    await modal.locator('#mp-insert').click();
    await modal.waitFor({state: 'hidden'});
    assert.equal(await page.locator('[data-ml-value]').inputValue(), '[2]');

    // Lista: dos consultas concurrentes, la antigua responde al final.
    await open({kind: 'images'});
    await page.route('**/ajax/admin/media/list', async route => {
      const response = await route.fetch();
      if (new URLSearchParams(route.request().postData()).get('q') === 'Portada') await new Promise(resolve => setTimeout(resolve, 600));
      await route.fulfill({response});
    });
    await page.evaluate(() => {
      const lib = $('[data-ml-mount]').data('mediaLibrary');
      lib.$mount.find('[data-ml-filter="q"]').val('Portada'); lib.load(1, {push: false});
      lib.$mount.find('[data-ml-filter="q"]').val('Segunda'); lib.load(1, {push: false});
    });
    await page.waitForTimeout(1000);
    assert.equal(await modal.locator('.m-list-card').count(), 1);
    assert.equal(await modal.locator('.m-list-card').getAttribute('data-media-id'), '2');
    await page.unroute('**/ajax/admin/media/list');
    await close();

    // Subida retrasada de una sesión cerrada no selecciona nada al reabrir.
    await open({kind: 'images'});
    await page.route('**/ajax/admin/media/upload', async route => {
      const response = await route.fetch();
      await new Promise(resolve => setTimeout(resolve, 700));
      await route.fulfill({response});
    });
    await modal.locator('[data-bs-target="#mp-upload"]').click();
    await modal.locator('[data-ml-file]').setInputFiles({name: 'tardia.png', mimeType: 'image/png', buffer: png});
    await close();
    await open({kind: 'images'});
    await page.waitForTimeout(1100);
    assert.equal(await modal.locator('#mp-insert').isDisabled(), true);
    assert.equal(await modal.locator('.is-selected').count(), 0);
    await page.unroute('**/ajax/admin/media/upload');
    await close();

    // Anchura móvil y teclado, con plantilla y CSS originales.
    await page.setViewportSize({width: 390, height: 844});
    await open({multiple: true, selected: [1, 2], kind: 'images'});
    await modal.locator('[data-mp-selection-id="2"]').waitFor();
    assert.ok(await modal.evaluate(el => el.scrollWidth <= el.clientWidth + 1));
    await modal.locator('[data-mp-detail="1"]').click();
    await modal.locator('#mp-original-name').waitFor({state: 'visible'});
    const screenshots = process.env.GFRAME_TEST_SCREENSHOTS;
    if (screenshots) await page.screenshot({path: join(screenshots, 'media-selector-mobile.png'), fullPage: true});
    await page.setViewportSize({width: 1440, height: 1000});
    if (screenshots) await page.screenshot({path: join(screenshots, 'media-selector-desktop.png'), fullPage: true});
    await close();
    assert.deepEqual(errors, []);
  } catch (error) {
    error.message += '\nPHP fixture:\n' + logs;
    throw error;
  } finally {
    await browser.close();
    server.kill();
    await new Promise(resolve => server.once('exit', resolve));
    assert.ok(resolve(root).startsWith(resolve(tmpdir()) + require('node:path').sep));
    rmSync(root, {recursive: true, force: true});
  }
});
