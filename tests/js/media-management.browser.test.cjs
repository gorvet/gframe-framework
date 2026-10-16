const assert = require('node:assert/strict');
const {test} = require('node:test');
const {spawn} = require('node:child_process');
const {mkdtempSync, writeFileSync, unlinkSync, rmSync, readFileSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join} = require('node:path');
const net = require('node:net');

test('Gestión real: lista, URL, navegación, guardado, errores y reproductores', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 90000}, async () => {
  const root = mkdtempSync(join(tmpdir(), 'gframe-management-test-'));
  const listener = net.createServer();
  await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
  const port = listener.address().port;
  await new Promise(resolve => listener.close(resolve));
  const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
  const server = spawn(php, ['-S', `127.0.0.1:${port}`, join(__dirname, '../fixtures/media-selector.php')], {env: {...process.env, GFRAME_MEDIA_TEST_ROOT: root}, windowsHide: true});
  let logs = '';
  server.stderr.on('data', value => logs += value.toString());
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  try {
    const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
    page.setDefaultTimeout(10000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const base = `http://127.0.0.1:${port}`;
    for (let i = 0; i < 60; i++) {
      try { if ((await fetch(base)).ok) break; } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    await page.goto(base + '/admin?source=navigation&view=list&sort=name_asc');
    const mount = page.locator('#knowledgeMediaList'), modal = page.locator('#editMediaModal');
    const active = id => page.waitForFunction(id => Number(document.querySelector('#media_id').value) === id && !document.querySelector('#media-original-name').disabled, id);
    const open = async id => {
      await page.evaluate(() => { window.detailsShown = false; $('#editMediaModal').one('shown.bs.modal', () => window.detailsShown = true); });
      await mount.locator(`[data-media-id="${id}"]`).click();
      await page.waitForFunction(() => window.detailsShown);
      await active(id);
    };
    const close = async () => { await modal.locator('[data-bs-dismiss]').first().click(); await modal.waitFor({state: 'hidden'}); };
    assert.equal(await mount.locator('table tbody tr').count(), 24);
    assert.equal(await mount.locator('.m-list-card').first().textContent().then(s => s.trim()), 'Documento 01');
    await page.locator('#media-sort').selectOption('size_desc');
    await page.waitForFunction(() => document.querySelector('#knowledgeMediaList .m-list-card')?.dataset.mediaId === '28');
    assert.match(page.url(), /sort=size_desc/);
    await page.reload();
    assert.equal(await mount.locator('.m-list-card').first().getAttribute('data-media-id'), '28');
    await page.locator('#media-sort').selectOption('name_asc');
    await page.waitForFunction(() => document.querySelector('#knowledgeMediaList .m-list-card')?.dataset.mediaId === '3');
    // Teclado activa una sola consulta y la navegación cruza el límite de 24 filas.
    let detailRequests = 0;
    page.on('request', req => { if (req.url().endsWith('/details')) ++detailRequests; });
    await mount.locator('[data-media-id="26"]').press('Space');
    await active(26); await modal.waitFor({state: 'visible'});
    await page.waitForTimeout(350);
    assert.equal(detailRequests, 1);
    await modal.locator('#editMediaNext').press('Enter'); await active(27);
    assert.match(page.url(), /page=2/);
    await modal.locator('#editMediaNext').click(); await active(28);
    assert.equal(await modal.locator('#editMediaNext').isDisabled(), true);
    await modal.locator('#editMediaPrev').click(); await active(27);
    await modal.locator('#editMediaPrev').click(); await active(26);
    assert.equal(await mount.locator('table tbody tr').count(), 24);
    await modal.locator('#media-original-name').fill('Edición pendiente');
    await modal.locator('#editMediaNext').click();
    assert.equal(await modal.locator('#media_id').inputValue(), '26');
    assert.match(await modal.locator('#media-details-status').textContent(), /Guarda o descarta/);
    await modal.locator('[data-bs-dismiss]').first().click();
    assert.equal(await modal.isVisible(), true);
    await modal.locator('#media-details-discard').click();
    assert.equal(await modal.locator('#media-original-name').inputValue(), 'Documento 24');
    // Fallo de permisos inyectado: conservar edición, sin escritura en SQLite.
    writeFileSync(join(root, 'deny-save'), '1');
    await modal.locator('#media-original-name').fill('Nombre rechazado');
    await modal.locator('#editMedia_guardar').click();
    await page.waitForFunction(() => window.lastToast?.icon === 'error');
    const unchanged = await page.request.post(base + '/ajax/admin/media/details', {form: {media_id: 26}});
    assert.equal((await unchanged.json()).data.original_name, 'Documento 24');
    unlinkSync(join(root, 'deny-save'));
    await modal.locator('#media-original-name').fill(' <b>Documento 24 revisado</b> ');
    await modal.locator('#alt_text').fill('Descripción guardada');
    await page.route('**/ajax/admin/media/save', async route => {
      const response = await route.fetch(); await new Promise(resolve => setTimeout(resolve, 600)); await route.fulfill({response});
    });
    await modal.locator('#editMedia_guardar').click();
    assert.equal(await modal.locator('#editMediaNext').isDisabled(), true);
    await modal.locator('[data-bs-dismiss]').first().click();
    assert.equal(await modal.isVisible(), true);
    await page.waitForFunction(() => window.lastToast?.icon === 'success');
    await page.unroute('**/ajax/admin/media/save');
    assert.equal(await modal.locator('#media-original-name').inputValue(), 'Documento 24 revisado');
    await close();
    await page.waitForFunction(() => document.activeElement?.dataset.mediaId === '26');
    // Extremo inicial y errores de detalle; la edición anterior no se atribuye al destino fallido.
    await open(3); assert.equal(await modal.locator('#editMediaPrev').isDisabled(), true);
    await page.route('**/ajax/admin/media/details', async route => {
      if (new URLSearchParams(route.request().postData()).get('media_id') === '4') await route.fulfill({status: 500, contentType: 'application/json', body: '{}'});
      else await route.continue();
    });
    await modal.locator('#editMediaNext').click();
    await page.waitForFunction(() => !document.querySelector('#media-original-name').disabled);
    assert.equal(await modal.locator('#media_id').inputValue(), '3');
    await page.unroute('**/ajax/admin/media/details');
    // Cerrar durante una consulta y reabrir otro archivo: respuesta antigua ignorada.
    await page.route('**/ajax/admin/media/details', async route => {
      const response = await route.fetch();
      if (new URLSearchParams(route.request().postData()).get('media_id') === '4') await new Promise(resolve => setTimeout(resolve, 800));
      await route.fulfill({response});
    });
    await modal.locator('#editMediaNext').click(); await close(); await open(5);
    await page.waitForTimeout(900); assert.equal(await modal.locator('#media_id').inputValue(), '5');
    await close(); await page.unroute('**/ajax/admin/media/details');
    // Lista vacía, una página, cuadrícula y recuperación con Atrás.
    await page.locator('#media-search-input').fill('No existe este archivo');
    await page.waitForFunction(() => !!document.querySelector('#noM'));
    assert.equal(await mount.locator('[data-ml-pagination]').count(), 0);
    await page.locator('#media-search-input').fill('Documento 01');
    await page.waitForFunction(() => document.querySelectorAll('#knowledgeMediaList tbody tr').length === 1);
    assert.equal(await mount.locator('[data-ml-pagination]').count(), 0);
    await page.locator('#media-view').selectOption('grid');
    await mount.locator('.m-list-card .card').waitFor();
    await page.goBack();
    await mount.locator('table').waitFor();
    // Medios válidos mediante el mismo controlador de subida.
    const uploaded = [];
    for (const file of [{name: 'Prueba.mp3', mimeType: 'audio/mpeg', buffer: readFileSync(join(__dirname, '../fixtures/media-preview.mp3'))}, {name: 'Prueba.webm', mimeType: 'video/webm', buffer: readFileSync(join(__dirname, '../fixtures/media-preview.webm'))}]) {
      const result = await page.request.post(base + '/ajax/admin/media/upload', {multipart: {file}});
      const payload = await result.json(); assert.equal(payload.status, 'success', JSON.stringify(payload)); uploaded.push(payload.data.media_id);
    }
    await page.goto(base + '/admin?view=list');
    await open(uploaded[0]);
    const audio = modal.locator('audio');
    assert.equal(await audio.isVisible(), true); assert.equal(await audio.getAttribute('autoplay'), null);
    await audio.evaluate(el => el.play()); await page.waitForFunction(() => document.querySelector('audio').readyState >= 2);
    await close(); assert.equal(await audio.getAttribute('src'), null);
    await open(uploaded[1]);
    const video = modal.locator('video');
    assert.equal(await video.isVisible(), true); assert.equal(await video.getAttribute('autoplay'), null);
    await video.evaluate(el => el.play()); await page.waitForFunction(() => document.querySelector('video').videoWidth === 32);
    await modal.locator('#editMediaNext').click(); await active(uploaded[0]);
    assert.equal(await video.getAttribute('src'), null);
    assert.match(await modal.locator('#media-open-file').getAttribute('href'), /^http:\/\/127\.0\.0\.1/);
    await close();
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-management-desktop.png'), fullPage: true});
    await page.setViewportSize({width: 390, height: 844});
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-management-mobile-list.png')});
    await open(uploaded[1]);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    const duplicateIDs = await page.evaluate(() => { const ids = [...document.querySelectorAll('[id]')].map(el => el.id); return ids.filter((id, i) => ids.indexOf(id) !== i); });
    assert.deepEqual(duplicateIDs, []);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-management-mobile.png')});
    const footerBox = await modal.locator('.modal-footer').boundingBox();
    assert.ok(footerBox.y + footerBox.height <= 844);
    await close();
    await page.setViewportSize({width: 768, height: 1024});
    await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'dark'));
    await open(uploaded[0]);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-management-dark-tablet.png')});
    await close(); assert.deepEqual(errors, []);
  } catch (error) {
    error.message += '\nPHP: ' + logs.slice(-3500); throw error;
  } finally {
    await browser.close(); server.kill(); await new Promise(resolve => server.once('exit', resolve)); rmSync(root, {recursive: true, force: true});
  }
});
