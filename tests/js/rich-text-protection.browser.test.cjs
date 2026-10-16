const {test} = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');

test('Protección real: salida, recuperación, confirmación, pestañas, cuota y navegación', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 90000}, async () => {
  const root = join(__dirname, '../..').replaceAll('\\', '/');
  const view = JSON.stringify(root + '/resources/modules/rich-text-editor/application/app/views/rich-text-editor/richTextEditor.php');
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
  const errors = []; context.on('page', page => page.on('pageerror', error => errors.push(error.message)));
  await context.route('**/*', async route => {
    const url = new URL(route.request().url()), path = url.pathname;
    if (path === '/') {
      const actor = url.searchParams.get('actor') === 'other' ? 'other' : 'one';
      const protect = url.searchParams.get('protect') === 'off' ? 'false' : 'true';
      const documentID = url.searchParams.has('other-document') ? '43' : '42';
      const fieldID = url.searchParams.has('other-field') ? 'article-summary' : 'article-content';
      const key = url.searchParams.has('no-drafts') ? "''" : `'tenant:7:actor:${actor}:article:${documentID}:content'`;
      const field = execFileSync(process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe', ['-r', `$richTextEditor=['id'=>'${fieldID}','name'=>'content','value'=>'<p>Servidor</p>','draft_key'=>${key},'protect_changes'=>${protect}]; include ${view};`], {encoding: 'utf8'});
      return route.fulfill({contentType: 'text/html', body: `<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/sweetalert.css"><link rel="stylesheet" href="/sweet-theme.css"><link rel="stylesheet" href="/public/vendors/external/tinymce/gframe-tinymce.css"><body><main class="container-fluid p-3"><h1 class="h4">Artículo</h1><form id="article-form">${field}<button id="save" type="submit">Guardar</button></form></main><script>var site_url='http://editor-protection.test/';document.getElementById('article-form').addEventListener('submit',e=>e.preventDefault());</script><script src="/jquery.js"></script><script src="/public/vendors/external/tinymce/tinymce.min.js"></script><script src="/sweetalert.js"></script><script src="/alerts.js"></script><script src="/editor.js"></script></body></html>`});
    }
    const mappings = {'/bootstrap.css': 'bootstrap/public/css/bootstrap.min.css', '/jquery.js': 'jquery/public/jquery.min.js', '/editor.js': 'rich-text-editor/public/rich-text-editor.js', '/sweetalert.js': 'sweetalert2/public/sweetalert2.all.min.js', '/alerts.js': 'alerts/public/alertToast.js', '/sweetalert.css': 'sweetalert2/public/sweetalert2.min.css', '/sweet-theme.css': 'sweetalert2/public/sweetTheme.css'};
    const relative = mappings[path] || (path.startsWith('/public/vendors/external/tinymce/') && !path.includes('..') ? 'tinymce/public/' + path.slice('/public/vendors/external/tinymce/'.length) : null);
    if (!relative) return route.fulfill({status: 404, body: ''});
    return route.fulfill({contentType: path.endsWith('.js') ? 'application/javascript' : path.endsWith('.css') ? 'text/css' : 'application/octet-stream', body: readFileSync(join(root, 'resources/modules', relative))});
  });
  const ready = (page, id = 'article-content') => page.waitForFunction(id => tinymce?.get(id)?.initialized && AdminRichTextEditor.getSaveSnapshot(id), id);
  const content = page => page.evaluate(() => tinymce.get('article-content').getContent());
  const set = (page, html) => page.evaluate(html => { const e = tinymce.get('article-content'); e.setContent(html); e.focus(); }, html);
  const records = page => page.evaluate(() => Object.keys(localStorage).filter(key => key.startsWith('gframe:rte:')).map(key => ({key, raw: localStorage.getItem(key)})));
  async function reload(page, accept) {
    let dialogs = 0;
    const handler = async dialog => { assert.equal(dialog.type(), 'beforeunload'); dialogs++; await (accept ? dialog.accept() : dialog.dismiss()); };
    page.on('dialog', handler);
    await page.reload({waitUntil: 'domcontentloaded', timeout: accept ? 10000 : 2500}).catch(error => { if (accept || !dialogs) throw error; });
    page.off('dialog', handler);
    if (accept) await ready(page);
    return dialogs;
  }
  try {
    const page = await context.newPage(); await page.goto('http://editor-protection.test/'); await ready(page);
    const body = page.frameLocator('#article-content_ifr').locator('body');
    await body.click(); await body.press('Control+End'); await body.pressSequentially(' texto pendiente');
    await page.waitForFunction(() => Object.keys(localStorage).some(key => (localStorage.getItem(key) || '').includes('texto pendiente')));
    const pending = await content(page);
    assert.equal(await page.evaluate(() => AdminRichTextEditor.hasUnsavedChanges('article-content') && AdminRichTextEditor.hasUnsavedChanges()), true);
    await page.evaluate(() => { AdminRichTextEditor.saveAll(); document.getElementById('save').click(); });
    assert.equal(await reload(page, false), 1, 'Sincronizar textarea o prevenir submit no confirma guardado.');
    assert.equal(await content(page), pending);
    assert.equal(await reload(page, true), 1);
    const recovery = page.locator('#article-content-recovery');
    await recovery.waitFor({state: 'visible'});
    await page.setViewportSize({width: 390, height: 844});
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'rich-text-recovery-mobile.png')});
    await page.setViewportSize({width: 1440, height: 1000});
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'rich-text-recovery.png')});
    assert.equal(await content(page), '<p>Servidor</p>', 'No sustituye automáticamente la versión del servidor.');
    await page.evaluate(() => tinymce.get('article-content').mode.set('readonly'));
    assert.equal(await recovery.locator('[data-editor-draft-restore]').isDisabled(), true);
    await page.evaluate(() => tinymce.get('article-content').mode.set('design'));
    await recovery.getByRole('button', {name: 'Recuperar borrador', exact: true}).click();
    assert.equal(await content(page), pending);
    await page.evaluate(() => tinymce.get('article-content').undoManager.undo());
    assert.equal(await content(page), '<p>Servidor</p>');
    await page.evaluate(() => tinymce.get('article-content').undoManager.redo());
    assert.equal(await content(page), pending);
    await body.click(); await body.press('Control+End'); await body.pressSequentially(' revisión recuperada');
    await page.evaluate(() => { window.sent = AdminRichTextEditor.getSaveSnapshot('article-content'); });
    await body.click(); await body.press('Control+End'); await body.pressSequentially(' después del envío');
    assert.equal(await page.evaluate(() => AdminRichTextEditor.markSaved(window.sent)), true);
    assert.equal(await reload(page, false), 1, 'Confirmar un envío no limpia las ediciones posteriores.');
    const later = await content(page);
    assert.equal(await reload(page, true), 1);
    await recovery.waitFor({state: 'visible'});
    assert.match(await recovery.textContent(), /contenido guardado ha cambiado/);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'rich-text-recovery-conflict.png')});
    await recovery.getByRole('button', {name: 'Recuperar borrador', exact: true}).click();
    assert.equal(await content(page), later);
    assert.equal(await page.evaluate(() => AdminRichTextEditor.markSaved(AdminRichTextEditor.getSaveSnapshot('article-content'))), true);
    assert.equal(await page.evaluate(() => AdminRichTextEditor.hasUnsavedChanges()), false);
    assert.equal(await reload(page, true), 0, 'El snapshot confirmado actual no pide salir.');
    assert.equal(await recovery.isVisible(), false);

    // Copias reales de dos pestañas: claves separadas, y otro actor no las ve.
    await body.click(); await set(page, '<p>Borrador pestaña A</p>');
    await page.waitForTimeout(750);
    const other = await context.newPage(); await other.goto('http://editor-protection.test/'); await ready(other);
    assert.equal(await other.locator('#article-content-recovery').isVisible(), true);
    assert.equal(await content(other), '<p>Servidor</p>');
    await other.frameLocator('#article-content_ifr').locator('body').click(); await set(other, '<p>Borrador pestaña B</p>');
    await other.waitForTimeout(750);
    let copies = await records(page);
    assert.equal(copies.length, 2); assert.notEqual(copies[0].key, copies[1].key);
    const foreign = await context.newPage(); await foreign.goto('http://editor-protection.test/?actor=other'); await ready(foreign);
    assert.equal(await foreign.locator('#article-content-recovery').isVisible(), false);
    assert.equal(await content(foreign), '<p>Servidor</p>');
    const otherDocument = await context.newPage(); await otherDocument.goto('http://editor-protection.test/?other-document'); await ready(otherDocument);
    assert.equal(await otherDocument.locator('#article-content-recovery').isVisible(), false);
    const otherField = await context.newPage(); await otherField.goto('http://editor-protection.test/?other-field'); await ready(otherField, 'article-summary');
    assert.equal(await otherField.locator('#article-summary-recovery').isVisible(), false);

    // Descartar requiere confirmar y no elimina la copia propia de B.
    const panelB = other.locator('#article-content-recovery');
    await other.evaluate(() => { window.confirmHelper = window.swalAlert; window.swalAlert = undefined; });
    await panelB.getByRole('button', {name: 'Descartar copia local', exact: true}).first().click();
    assert.equal((await records(other)).length, 2);
    assert.match(await other.locator('#article-content-draft-status').textContent(), /confirmación no está disponible/);
    await other.evaluate(() => { window.swalAlert = window.confirmHelper; });
    await panelB.getByRole('button', {name: 'Descartar copia local', exact: true}).first().click();
    if (process.env.GFRAME_TEST_SCREENSHOTS) await other.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'rich-text-discard-confirm.png')});
    await other.getByRole('dialog').getByRole('button', {name: 'Cancelar', exact: true}).click();
    assert.equal((await records(other)).length, 2);
    await panelB.getByRole('button', {name: 'Descartar copia local', exact: true}).first().click();
    await other.getByRole('dialog').getByRole('button', {name: 'Descartar copia local', exact: true}).click();
    await other.getByRole('dialog').waitFor({state: 'hidden'});
    copies = await records(other); assert.equal(copies.length, 1); assert.match(copies[0].raw, /pesta&ntilde;a B/);
    assert.match(await content(page), /pesta&ntilde;a A/, 'Descartar una copia no cambia el texto de otra pestaña.');

    // Respuestas tardías no confirman una nueva instancia ni aceptan snapshots inventados.
    await other.evaluate(() => {
      tinymce.get('article-content').setContent('<p>Cambio justo antes de retirar</p>');
      window.oldSnapshot = AdminRichTextEditor.getSaveSnapshot('article-content');
      AdminRichTextEditor.destroy('article-content'); AdminRichTextEditor.init(document.getElementById('article-content'));
    });
    await ready(other);
    const removedCopies = await records(other);
    assert.ok(removedCopies.some(copy => copy.raw.includes('justo antes de retirar')), 'Destroy conserva incluso el cambio anterior a la pausa del temporizador.');
    await other.waitForTimeout(750);
    assert.deepEqual(await records(other), removedCopies, 'La instancia retirada no vuelve a escribir desde su temporizador.');
    assert.equal(await other.evaluate(() => AdminRichTextEditor.markSaved(window.oldSnapshot)), false);
    assert.equal(await other.evaluate(() => AdminRichTextEditor.markSaved({editorID: 'article-content', content: '<p>Servidor</p>'})), false);

    // Entradas inválidas/caducadas: no ejecución ni restauración automática.
    const prefix = copies[0].key.slice(0, copies[0].key.lastIndexOf(':') + 1);
    await foreign.evaluate(prefix => {
      localStorage.setItem(prefix + 'expired', JSON.stringify({version: 1, content: '<p>Viejo</p>', baseline: '', updated: Date.now() - 8 * 86400000}));
      localStorage.setItem(prefix + 'invalid', '{');
      localStorage.setItem(prefix + 'hostile', JSON.stringify({version: 1, content: '<script>window.executed=true</script><img src=x onerror="window.executed=true"><p>Recuperable</p>', baseline: '', updated: Date.now() + 1}));
    }, prefix);
    const hostile = await context.newPage(); await hostile.goto('http://editor-protection.test/'); await ready(hostile);
    assert.equal(await hostile.evaluate(() => window.executed), undefined);
    assert.equal(await hostile.evaluate(prefix => localStorage.getItem(prefix + 'expired'), prefix), null);
    await hostile.locator('[data-editor-draft-restore]').click();
    assert.equal(await hostile.evaluate(() => window.executed || tinymce.get('article-content').getWin().executed), undefined);
    assert.match(await content(hostile), /Recuperable/); assert.doesNotMatch(await content(hostile), /<script|onerror/);

    const quota = await context.newPage(); await quota.goto('http://editor-protection.test/?actor=other'); await ready(quota);
    await quota.evaluate(() => { window.originalStorageSetItem = Storage.prototype.setItem; Storage.prototype.setItem = function() { throw new DOMException('quota', 'QuotaExceededError'); }; });
    await quota.frameLocator('#article-content_ifr').locator('body').click(); await set(quota, '<p>Texto sin cuota</p>');
    await quota.waitForTimeout(750);
    assert.match(await quota.locator('#article-content-draft-status').textContent(), /No se pudo actualizar/);
    assert.match(await content(quota), /Texto sin cuota/); assert.equal(await reload(quota, false), 1);

    // Límite propio, sin depender de la cuota total del navegador.
    await quota.evaluate(() => { Storage.prototype.setItem = window.originalStorageSetItem; });
    await set(quota, '<p>' + 'a'.repeat(600000) + '</p>'); await quota.waitForTimeout(750);
    assert.match(await quota.locator('#article-content-draft-status').textContent(), /No se pudo actualizar/);
    assert.ok((await content(quota)).length > 600000);
    assert.ok((await records(quota)).every(copy => copy.raw.length < 600000));

    const plain = await context.newPage(); await plain.goto('http://editor-protection.test/?no-drafts&protect=off'); await ready(plain);
    await plain.frameLocator('#article-content_ifr').locator('body').click(); await set(plain, '<p>Sin aviso optativo</p>');
    assert.equal(await reload(plain, true), 0);
    assert.equal(await plain.locator('#article-content-recovery').count(), 0);
    await set(plain, '<p>Envío anterior</p>');
    await plain.evaluate(() => { window.firstSnapshot = AdminRichTextEditor.getSaveSnapshot('article-content'); });
    await set(plain, '<p>Envío más reciente</p>');
    assert.equal(await plain.evaluate(() => AdminRichTextEditor.markSaved(AdminRichTextEditor.getSaveSnapshot('article-content'))), true);
    assert.equal(await plain.evaluate(() => AdminRichTextEditor.markSaved(window.firstSnapshot)), false);
    assert.equal(await plain.evaluate(() => Object.isFrozen(window.firstSnapshot)), true);
    const html = '<h2>Primera sección</h2>' + '<p>Contenido largo para navegar</p>'.repeat(70) + '<h3>Última sección</h3>';
    await set(plain, html); const before = await content(plain);
    await plain.getByRole('button', {name: 'Esquema del artículo', exact: true}).focus();
    await plain.getByRole('button', {name: 'Esquema del artículo', exact: true}).press('Enter');
    await plain.getByRole('menuitem', {name: 'Última sección', exact: true}).focus();
    await plain.getByRole('menuitem', {name: 'Última sección', exact: true}).press('Enter');
    assert.ok(await plain.evaluate(() => tinymce.get('article-content').getDoc().scrollingElement.scrollTop) > 0);
    assert.equal(await content(plain), before);
    const fullscreen = plain.getByRole('button', {name: /Pantalla completa/});
    await fullscreen.focus(); await fullscreen.press('Enter');
    assert.equal(await plain.locator('body').evaluate(body => body.classList.contains('tox-fullscreen')), true);
    await fullscreen.focus(); await fullscreen.press('Enter');
    assert.equal(await plain.locator('body').evaluate(body => body.classList.contains('tox-fullscreen')), false);
    await plain.setViewportSize({width: 390, height: 844});
    assert.equal(await plain.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (process.env.GFRAME_TEST_SCREENSHOTS) {
      await quota.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'rich-text-storage-failure.png')});
      await plain.screenshot({path: join(process.env.GFRAME_TEST_SCREENSHOTS, 'rich-text-protection-mobile.png')});
    }
    assert.deepEqual(errors, []);
  } finally { await context.close(); await browser.close(); }
});
