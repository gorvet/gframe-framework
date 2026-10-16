const assert = require('node:assert/strict');
const {test} = require('node:test');
const {spawn} = require('node:child_process');
const {mkdtempSync, rmSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join} = require('node:path');

test('TinyMCE real: biblioteca, galerías, cursor, undo, saneado y destrucción', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 90000}, async () => {
  const root = mkdtempSync(join(tmpdir(), 'gframe-writing-test-'));
  const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
  const fixture = join(__dirname, '../fixtures/media-selector.php');
  // Sin sockets: Playwright transporta cada petición a la fixture PHP real por un proceso CLI.
  // Esto prueba TinyMCE/controlador/SQLite; no certifica transporte HTTP ni middleware.
  const base = 'http://media-writing.test';
  const dispatch = (url, method = 'GET', body = '') => new Promise((resolve, reject) => {
    const input = Buffer.from(JSON.stringify({path: new URL(url, base).pathname + new URL(url, base).search, method, body, fixture})).toString('base64');
    const code = '$r=json_decode(base64_decode($argv[1]),true); $_SERVER["REQUEST_URI"]=$r["path"]; $_SERVER["SERVER_PORT"]=80; $_SERVER["REQUEST_METHOD"]=$r["method"]; parse_str(parse_url($r["path"], PHP_URL_QUERY)??"",$_GET); parse_str($r["body"],$_POST); $_REQUEST=array_merge($_GET,$_POST); include $r["fixture"];';
    const child = spawn(php, ['-r', code, input], {env: {...process.env, GFRAME_MEDIA_TEST_ROOT: root, GFRAME_MEDIA_TEST_URL: base + '/', GFRAME_MEDIA_TEST_IMAGE: join(__dirname, '../fixtures/media-writing-image.png')}, windowsHide: true});
    const chunks = []; let errors = '';
    child.stdout.on('data', data => chunks.push(data)); child.stderr.on('data', data => errors += data.toString());
    child.on('error', reject); child.on('exit', exit => exit ? reject(new Error(errors)) : resolve(Buffer.concat(chunks)));
  });
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  try {
    const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
    page.setDefaultTimeout(10000);
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const fulfill = async route => {
      const req = route.request(), body = await dispatch(req.url(), req.method(), req.postData() || '');
      const path = new URL(req.url()).pathname;
      const contentType = req.method() === 'POST' ? 'application/json' : path.endsWith('.js') ? 'application/javascript' : path.endsWith('.css') ? 'text/css' : path.endsWith('.png') ? 'image/png' : path.endsWith('.woff') ? 'font/woff' : 'text/html';
      await route.fulfill({status: 200, body, contentType});
    };
    await page.route('**/*', fulfill);
    await page.goto(base + '/editor');
    const ready = () => page.waitForFunction(() => ['writing-one', 'writing-two', 'writing-plain'].every(id => window.tinymce?.get(id)?.initialized));
    await ready();
    const modal = page.locator('#mediaPickerModal');
    const content = id => page.evaluate(id => tinymce.get(id).getContent(), id || 'writing-one');
    const select = async (ids, command = 'gframeGallery', editorID = 'writing-one', keyboard = false) => {
      await page.evaluate(({command, editorID, keyboard}) => {
        window.pickerShown = false; $('#mediaPickerModal').one('shown.bs.modal', () => window.pickerShown = true);
        if (!keyboard) tinymce.get(editorID).execCommand(command);
      }, {command, editorID, keyboard});
      if (keyboard) await page.getByRole('button', {name: 'Imagen de biblioteca', exact: true}).first().press('Enter');
      await page.waitForFunction(() => window.pickerShown);
      await modal.locator('.m-list-card').first().waitFor();
      for (const id of ids) await modal.locator(`[data-media-id="${id}"]`).press('Enter');
    };
    const insert = async () => { await modal.locator('#mp-insert').click(); await modal.waitFor({state: 'hidden'}); await page.waitForTimeout(120); };
    const waitImages = count => page.waitForFunction(count => tinymce.get('writing-one').getBody().querySelectorAll('img').length === count, count);
    const galleryIDs = () => page.evaluate(() => [...tinymce.get('writing-one').getBody().querySelectorAll('figure[data-media-gallery] img')].map(el => Number(el.dataset.mediaId)));
    assert.equal(await page.locator('#writing-plain').evaluate(el => !!tinymce.get(el.id).ui.registry.getAll().buttons.gframeimage), false);
    assert.equal(await page.locator('#writing-one').evaluate(el => !!tinymce.get(el.id).ui.registry.getAll().buttons.gframeimage), true);
    // Insertar dentro del párrafo sin perder texto ni posición.
    await page.evaluate(() => {
      const e = tinymce.get('writing-one'); e.focus(); const text = e.getBody().querySelector('p').firstChild;
      const range = e.getDoc().createRange(); range.setStart(text, 6); range.collapse(true); e.selection.setRng(range);
    });
    await page.evaluate(() => tinymce.get('writing-one').execCommand('mceFullScreen'));
    await select([1], 'gframeImage', 'writing-one', true);
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('tox-fullscreen')), false, 'La biblioteca no queda debajo del editor en concentración.');
    await insert(); await waitImages(1);
    await page.waitForFunction(() => tinymce.get('writing-one').plugins.fullscreen.isFullscreen());
    await page.evaluate(() => tinymce.get('writing-one').execCommand('mceFullScreen'));
    assert.equal(await page.evaluate(() => tinymce.get('writing-one').isDirty()), true, 'Insertar no equivale a guardar el artículo; el editor debe seguir marcando cambios pendientes.');
    let html = await content(); assert.ok(html.indexOf('Antes') < html.indexOf('<img'), html); assert.ok(html.indexOf('<img') < html.indexOf('despu'), html);
    assert.equal(await content('writing-two'), '<p>Segundo</p>');
    // Herramientas secundarias accesibles por teclado, sin una segunda fila permanente.
    await page.getByRole('button', {name: 'Más herramientas', exact: true}).first().press('Enter');
    await page.getByRole('button', {name: 'Subrayado', exact: false}).first().waitFor({state: 'visible'});
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-writing-tools.png')});
    await page.keyboard.press('Escape');
    assert.equal(await page.evaluate(() => tinymce.get('writing-one').isDirty()), true, 'La barra no debe limpiar cambios pendientes.');
    // Guardar conserva validación y estado dirty; no crea otro flujo de persistencia.
    await page.evaluate(() => tinymce.get('writing-one').execCommand('gframeSave'));
    assert.equal(await page.evaluate(() => window.writingSaves.length), 0, 'El título requerido debe seguir validándose.');
    assert.equal(await page.evaluate(() => tinymce.get('writing-one').isDirty()), true, 'La validación fallida no debe limpiar cambios.');
    await page.locator('#writing-title').fill('Artículo de prueba');
    await page.frameLocator('#writing-one_ifr').locator('body').press('Control+s');
    await page.waitForFunction(() => window.writingSaves.length === 1);
    assert.match(await page.evaluate(() => window.writingSaves[0]), /data-media-id="1"/);
    assert.equal(await page.evaluate(() => tinymce.get('writing-one').isDirty()), true);
    await page.evaluate(() => document.getElementById('writing-save').disabled = true);
    await page.waitForFunction(() => tinymce.get('writing-one').getContainer().querySelector('[aria-label="Guardar artículo"]')?.getAttribute('aria-disabled') === 'true');
    await page.evaluate(() => { tinymce.get('writing-one').execCommand('gframeSave'); tinymce.get('writing-two').execCommand('gframeSave'); });
    assert.equal(await page.evaluate(() => window.writingSaves.length), 1);
    assert.equal(await page.evaluate(() => window.otherSaves), 0);
    await page.evaluate(() => { document.getElementById('writing-save').disabled = false; tinymce.get('writing-one').mode.set('readonly'); tinymce.get('writing-one').execCommand('gframeSave'); });
    assert.equal(await page.evaluate(() => window.writingSaves.length), 1);
    await page.evaluate(() => tinymce.get('writing-one').mode.set('design'));
    await page.waitForFunction(() => tinymce.get('writing-one').getContainer().querySelector('[aria-label="Guardar artículo"]').getAttribute('aria-disabled') !== 'true');
    await page.getByRole('button', {name: 'Guardar artículo', exact: true}).first().click();
    await page.waitForFunction(() => window.writingSaves.length === 2);
    // Quitar desde el contexto solo modifica el texto y admite Deshacer.
    await page.frameLocator('#writing-one_ifr').locator('img').first().click();
    await page.getByRole('button', {name: 'Quitar imagen del texto', exact: true}).waitFor({state: 'visible'});
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-writing-image-context.png')});
    await page.getByRole('button', {name: 'Quitar imagen del texto', exact: true}).click();
    await waitImages(0);
    const retained = JSON.parse(await dispatch(base + '/ajax/admin/media/details', 'POST', 'media_id=1'));
    assert.equal(retained.status, 'success');
    await page.evaluate(() => tinymce.get('writing-one').undoManager.undo()); await waitImages(1);
    // Imagen seleccionada: sustituirla, no duplicarla.
    await page.evaluate(() => { const e = tinymce.get('writing-one'); e.selection.select(e.getBody().querySelector('img')); });
    await page.frameLocator('#writing-one_ifr').locator('img').first().click();
    await page.evaluate(() => { window.pickerShown = false; $('#mediaPickerModal').one('shown.bs.modal', () => window.pickerShown = true); });
    await page.getByRole('button', {name: 'Cambiar imagen', exact: true}).click();
    await page.waitForFunction(() => window.pickerShown);
    await modal.locator('[data-media-id="2"]').press('Enter'); await insert(); await waitImages(1);
    await page.waitForFunction(() => tinymce.get('writing-one').getBody().querySelector('img')?.dataset.mediaId === '2');
    assert.match(await content(), /data-media-id="2"/);
    await page.evaluate(() => tinymce.get('writing-one').undoManager.undo());
    assert.match(await content(), /data-media-id="1"/);
    await page.evaluate(() => tinymce.get('writing-one').undoManager.redo());
    assert.match(await content(), /data-media-id="2"/);
    // Insertar galería nueva al final y reabrir con su orden.
    await page.evaluate(() => { const e = tinymce.get('writing-one'); e.selection.select(e.getBody(), true); e.selection.collapse(false); });
    await select([1, 2]); await insert(); await waitImages(3);
    assert.deepEqual(await galleryIDs(), [1, 2]);
    await page.frameLocator('#writing-one_ifr').locator('figure[data-media-gallery] img').first().click();
    await page.getByRole('button', {name: 'Quitar galería del texto', exact: true}).waitFor({state: 'visible'});
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-writing-gallery-context.png')});
    await page.getByRole('button', {name: 'Quitar galería del texto', exact: true}).click();
    await waitImages(1);
    await page.evaluate(() => tinymce.get('writing-one').undoManager.undo()); await waitImages(3);
    await page.evaluate(() => { const e = tinymce.get('writing-one'); e.selection.select(e.getBody().querySelector('figure[data-media-gallery] img')); });
    await page.frameLocator('#writing-one_ifr').locator('figure[data-media-gallery] img').first().click();
    await page.evaluate(() => { window.pickerShown = false; $('#mediaPickerModal').one('shown.bs.modal', () => window.pickerShown = true); });
    await page.getByRole('button', {name: 'Editar galería', exact: true}).click();
    await page.waitForFunction(() => window.pickerShown);
    await modal.locator('[data-mp-selection-id="2"]').waitFor();
    assert.deepEqual(await modal.locator('[data-mp-selection-id]').evaluateAll(els => els.map(el => Number(el.dataset.mpSelectionId))), [1, 2]);
    await modal.locator('[data-mp-selection-id="2"] [data-mp-move="-1"]').press('Enter');
    await page.waitForFunction(() => document.querySelector('[data-mp-selection-id]')?.dataset.mpSelectionId === '2');
    await insert(); await page.waitForFunction(() => tinymce.get('writing-one').getBody().querySelector('figure[data-media-gallery] img')?.dataset.mediaId === '2');
    assert.deepEqual(await galleryIDs(), [2, 1]);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-writing-gallery.png')});
    assert.equal(await page.evaluate(() => tinymce.get('writing-one').getBody().querySelectorAll('figure[data-media-gallery]').length), 1);
    const beforeCancel = await content();
    await select([]); await modal.locator('[data-bs-dismiss]').first().click(); await modal.waitFor({state: 'hidden'});
    assert.equal(await content(), beforeCancel);
    // Guardado saneado por PHP, recarga y reedición de los IDs guardados.
    await page.evaluate(() => AdminRichTextEditor.saveAll());
    const saved = await dispatch(base + '/content', 'POST', new URLSearchParams({content: await page.locator('#writing-one').inputValue()}).toString());
    assert.equal(JSON.parse(saved).status, 'success');
    assert.match(JSON.parse(saved).data.html, /data-media-gallery="1"/);
    page.once('dialog', dialog => dialog.accept()); // La recarga de esta prueba descarta cambios deliberadamente; protección se verifica aparte.
    await page.reload(); await ready();
    assert.deepEqual(await galleryIDs(), [2, 1]);
    await page.evaluate(() => { const e = tinymce.get('writing-one'); e.selection.select(e.getBody().querySelector('figure[data-media-gallery] img')); });
    await select([]); await modal.locator('[data-mp-remove="1"]').click(); await insert();
    await page.waitForFunction(() => tinymce.get('writing-one').getBody().querySelectorAll('figure[data-media-gallery] img').length === 1);
    assert.deepEqual(await galleryIDs(), [2]);
    // No reconstruir una galería personalizada eliminando texto que haya añadido el autor.
    await page.evaluate(() => {
      const e = tinymce.get('writing-one'), gallery = e.getBody().querySelector('figure[data-media-gallery]');
      gallery.appendChild(e.getDoc().createTextNode('Texto que debe conservarse')); e.selection.select(gallery.querySelector('img'));
      window.lastToast = null; e.execCommand('gframeGallery');
    });
    assert.match(await content(), /Texto que debe conservarse/);
    assert.match(await page.evaluate(() => window.lastToast.title), /contiene texto/);
    assert.equal(await modal.isVisible(), false);
    await page.evaluate(() => {
      const gallery = tinymce.get('writing-one').getBody().querySelector('figure[data-media-gallery]');
      [...gallery.childNodes].filter(el => el.nodeType === 3 && el.textContent.includes('Texto que debe')).forEach(el => el.remove());
    });
    const beforeError = await content();
    await page.evaluate(() => { const e = tinymce.get('writing-one'); e.selection.select(e.getBody(), true); e.selection.collapse(false); window.lastToast = null; });
    await page.route('**/ajax/admin/media/field', async route => {
      if ((new URLSearchParams(route.request().postData()).get('variant') || '').startsWith('editor_')) {
        await route.fulfill({status: 403, contentType: 'application/json', body: '{"status":"unauthorized","code":"forbidden"}'});
      } else await route.fallback();
    });
    await select([1], 'gframeImage'); await insert();
    await page.waitForFunction(() => window.lastToast?.icon === 'warning');
    assert.equal(await content(), beforeError);
    await page.unroute('**/ajax/admin/media/field');
    // No insertar si cambia el documento mientras llega el HTML, ni al destruir la instancia.
    let releaseFirstEditor, firstEditorStarted;
    const editorGate = new Promise(resolve => releaseFirstEditor = resolve);
    const editorStarted = new Promise(resolve => firstEditorStarted = resolve);
    let holdFirstEditor = true;
    await page.route('**/ajax/admin/media/field', async route => {
      const body = await dispatch(route.request().url(), 'POST', route.request().postData());
      if ((new URLSearchParams(route.request().postData()).get('variant') || '').startsWith('editor_')) {
        if (holdFirstEditor) { holdFirstEditor = false; firstEditorStarted(); await editorGate; }
        else await new Promise(resolve => setTimeout(resolve, 600));
      }
      await route.fulfill({status: 200, contentType: 'application/json', body});
    });
    await page.evaluate(() => { const e = tinymce.get('writing-one'); e.selection.select(e.getBody(), true); e.selection.collapse(false); });
    await page.locator('#writing-title').fill('Artículo revisado');
    await page.evaluate(() => {
      AdminRichTextEditor.destroy('writing-two');
      document.getElementById('writing-two').dataset.editorSaveButton = 'writing-save';
      AdminRichTextEditor.init(document.getElementById('writing-two'));
    });
    await page.waitForFunction(() => tinymce.get('writing-two')?.initialized);
    await select([1], 'gframeImage'); await insert();
    await editorStarted;
    const pendingSaves = await page.evaluate(() => { const count = window.writingSaves.length; tinymce.get('writing-one').execCommand('gframeSave'); return count; });
    assert.equal(await page.evaluate(() => window.writingSaves.length), pendingSaves);
    await page.evaluate(() => tinymce.get('writing-two').execCommand('gframeSave'));
    assert.equal(await page.evaluate(() => window.writingSaves.length), pendingSaves, 'Otro editor del mismo formulario tampoco debe guardar un fragmento pendiente.');
    await page.evaluate(() => tinymce.get('writing-one').setContent('<p>Texto reciente</p>'));
    releaseFirstEditor();
    await page.waitForTimeout(800); assert.equal(await content(), '<p>Texto reciente</p>');
    await select([2], 'gframeImage'); await insert();
    await page.evaluate(() => { AdminRichTextEditor.destroy('writing-one'); AdminRichTextEditor.init(document.getElementById('writing-one')); });
    await page.waitForFunction(() => tinymce.get('writing-one')?.initialized);
    await page.waitForTimeout(800); assert.equal(await content(), '<p>Texto reciente</p>');
    await page.unroute('**/ajax/admin/media/field');
    // Instancia secundaria y usuario que solo cancela: no afecta al principal.
    await select([1], 'gframeImage', 'writing-two'); await insert();
    await page.waitForFunction(() => tinymce.get('writing-two').getBody().querySelectorAll('img').length === 1);
    assert.equal(await content(), '<p>Texto reciente</p>');
    await page.setViewportSize({width: 390, height: 844});
    await page.locator('#writing-two').evaluate(el => tinymce.get(el.id).getContainer().scrollIntoView());
    await select([], 'gframeGallery', 'writing-two');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-writing-mobile.png')});
    await modal.locator('[data-bs-dismiss]').first().click(); await modal.waitFor({state: 'hidden'});
    await page.setViewportSize({width: 1440, height: 1000});
    await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'dark'));
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'media-writing-desktop.png')});
    assert.deepEqual(errors, []);
  } finally { await browser.close(); rmSync(root, {recursive: true, force: true}); }
});
