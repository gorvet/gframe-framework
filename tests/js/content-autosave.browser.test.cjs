const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const {mkdtempSync, readFileSync, rmSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join} = require('node:path');

test('Autoguardado privado real: recuperación, petición en vuelo, conflictos y publicación', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 120000}, async () => {
  const root = mkdtempSync(join(tmpdir(), 'gframe-autosave-browser-'));
  const framework = join(__dirname, '../..');
  const fixture = join(__dirname, '../fixtures/content-autosave.php');
  const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
  const dispatch = (url, method = 'GET', body = '') => new Promise((resolve, reject) => {
    const input = Buffer.from(JSON.stringify({url, method, body, fixture})).toString('base64');
    const code = '$r=json_decode(base64_decode($argv[1]),true);$_SERVER["REQUEST_URI"]=parse_url($r["url"],PHP_URL_PATH);$_SERVER["REQUEST_METHOD"]=$r["method"];parse_str($r["body"],$_POST);$_REQUEST=$_POST;include $r["fixture"];';
    const child = spawn(php, ['-r', code, input], {env: {...process.env, GFRAME_AUTOSAVE_TEST_ROOT: root}, windowsHide: true});
    const chunks = []; let errors = ''; child.stdout.on('data', d => chunks.push(d)); child.stderr.on('data', d => errors += d);
    child.on('error', reject); child.on('exit', code => code ? reject(new Error(errors)) : resolve(Buffer.concat(chunks)));
  });
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
  await context.addInitScript(() => {
    window.__autosaveRequestTimes = [];
    const send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function(body) {
      const action = new URLSearchParams(body || '').get('action');
      if (action) window.__autosaveRequestTimes.push({action, time: performance.now()});
      return send.apply(this, arguments);
    };
  });
  const requests = [], errors = []; let hold, heldStarted, release, failSaves = 0, active = 0, maximum = 0;
  context.on('page', page => { page.on('pageerror', e => errors.push(e.message)); page.on('dialog', dialog => dialog.accept()); page.setDefaultTimeout(12000); });
  await context.route('**/*', async route => {
    const req = route.request(), path = new URL(req.url()).pathname;
    if (path === '/' || path === '/autosave' || path === '/publish') {
      const params = new URLSearchParams(req.postData() || '');
      const action = params.get('action');
      if (path === '/autosave') {
        requests.push({action, data: Object.fromEntries(params), time: Date.now()}); active++; maximum = Math.max(maximum, active);
        if (action === 'save' && failSaves-- > 0) { active--; return route.fulfill({status: 503, contentType:'application/json', body:'{}'}); }
      }
      const body = await dispatch(req.url(), req.method(), req.postData() || '');
      if (action === 'save' && hold) { const gate = hold; hold = null; heldStarted(); await gate; }
      if (path === '/autosave') active--;
      return route.fulfill({contentType: req.method() === 'POST' ? 'application/json' : 'text/html', body});
    }
    const mappings = {'/bootstrap.css':'bootstrap/public/css/bootstrap.min.css','/jquery.js':'jquery/public/jquery.min.js','/sweetalert.js':'sweetalert2/public/sweetalert2.all.min.js','/alerts.js':'alerts/public/alertToast.js','/sweetalert.css':'sweetalert2/public/sweetalert2.min.css','/sweet-theme.css':'sweetalert2/public/sweetTheme.css','/editor.js':'rich-text-editor/public/rich-text-editor.js'};
    const relative = mappings[path] || (path.startsWith('/public/vendors/external/tinymce/') && !path.includes('..') ? 'tinymce/public/' + path.slice('/public/vendors/external/tinymce/'.length) : null);
    if (!relative) return route.fulfill({status:404, body:''});
    return route.fulfill({contentType:path.endsWith('.css')?'text/css':path.endsWith('.js')?'application/javascript':'application/octet-stream', body:readFileSync(join(framework, 'resources/modules', relative))});
  });
  const inspect = async () => JSON.parse((await dispatch('http://content-autosave.test/inspect')).toString());
  const set = (page, html) => page.evaluate(html => {const e=tinymce.get('article-content');e.setContent(html);e.focus();}, html);
  const get = page => page.evaluate(() => tinymce.get('article-content').getContent());
  const status = page => page.locator('[data-autosave-status]');
  const ready = async page => { await page.waitForFunction(() => window.tinymce?.get('article-content')?.initialized); await status(page).filter({hasText:/Contenido guardado|Hay|Comprobando/}).waitFor(); };
  try {
    const page = await context.newPage(); await page.goto('http://content-autosave.test/'); await ready(page);
    await status(page).filter({hasText:'Contenido guardado'}).waitFor();
    const old = await inspect();
    await set(page, '<p>Privado inicial</p>');
    await status(page).filter({hasText:'Autoguardado privado actualizado'}).waitFor().catch(async error => { error.message += '\nEstado: ' + await status(page).textContent() + '\nPeticiones: ' + JSON.stringify(requests) + '\nErrores: ' + JSON.stringify(errors); throw new Error(error.message); });
    let state = await inspect(); assert.equal(state.article.content, old.article.content); assert.equal(state.draft.data.content, '<p>Privado inicial</p>');
    assert.equal(await page.evaluate(() => AdminRichTextEditor.hasUnsavedChanges()), true, 'Autoguardar no equivale a publicar.');
    assert.ok(requests.every(req => req.data.csrfToken === 'fixture-token'));
    const attempts = await page.evaluate(() => window.__autosaveRequestTimes);
    assert.ok(attempts.find(req => req.action === 'save').time - attempts[0].time >= 4800, 'Respeta el intervalo mínimo entre peticiones en el reloj del navegador.');
    await page.reload(); await ready(page);
    await page.locator('[data-autosave-recovery]').waitFor({state:'visible'});
    assert.equal(await get(page), '<p>Publicado</p>');
    await page.locator('[data-autosave-restore]').click(); assert.equal(await get(page), '<p>Privado inicial</p>');
    await page.evaluate(() => tinymce.get('article-content').undoManager.undo()); assert.equal(await get(page), '<p>Publicado</p>');
    await page.evaluate(() => tinymce.get('article-content').undoManager.redo());

    // Una respuesta antigua no reemplaza lo escrito durante la petición; solo una activa.
    let started = new Promise(resolve => heldStarted = resolve); hold = new Promise(resolve => release = resolve);
    await set(page, '<p>En vuelo</p>'); await started;
    await set(page, '<p>Texto posterior</p>');
    await page.waitForTimeout(1200); assert.equal(active, 1); assert.equal(await get(page), '<p>Texto posterior</p>');
    release(); await status(page).filter({hasText:'Autoguardado privado actualizado'}).waitFor();
    await page.waitForFunction(() => document.querySelector('[data-autosave-status]').textContent.includes('actualizado'));
    for (let i=0; i<15 && (await inspect()).draft.data.content !== '<p>Texto posterior</p>'; i++) await page.waitForTimeout(500);
    assert.equal((await inspect()).draft.data.content, '<p>Texto posterior</p>'); assert.equal(maximum, 1);

    failSaves = 1; await set(page, '<p>Tras fallo</p>');
    await status(page).filter({hasText:'No se pudo autoguardar'}).waitFor();
    assert.equal(await get(page), '<p>Tras fallo</p>');
    await status(page).filter({hasText:'Autoguardado privado actualizado'}).waitFor();
    assert.equal((await inspect()).draft.data.content, '<p>Tras fallo</p>');

    // Otra pestaña carga y consume la copia; una versión antigua no puede sobrescribirla.
    const second = await context.newPage(); await second.goto('http://content-autosave.test/'); await ready(second);
    await second.locator('[data-autosave-restore]').click(); await set(second, '<p>Otra pestaña</p>');
    for (let i=0; i<20 && (await inspect()).draft.data.content !== '<p>Otra pestaña</p>'; i++) await second.waitForTimeout(500);
    assert.equal((await inspect()).draft.data.content, '<p>Otra pestaña</p>');
    await set(page, '<p>Conflicto conservado</p>'); await status(page).filter({hasText:'Recarga la página'}).waitFor();
    assert.equal(await get(page), '<p>Conflicto conservado</p>'); assert.equal((await inspect()).draft.data.content, '<p>Otra pestaña</p>');
    started = new Promise(resolve => heldStarted = resolve); hold = new Promise(resolve => release = resolve);
    await set(second, '<p>Publicación en vuelo</p>'); await started;
    await second.locator('#publish').click(); await second.waitForFunction(() => window.published);
    await set(second, '<p>Texto después de publicar</p>'); release();
    await status(second).filter({hasText:'Contenido guardado'}).waitFor();
    state = await inspect(); assert.equal(state.article.content, '<p>Publicación en vuelo</p>'); assert.equal(state.draft.data.available, false);
    await status(second).filter({hasText:'Autoguardado privado actualizado'}).waitFor();
    assert.equal((await inspect()).draft.data.base_version, state.article.revision);
    assert.equal((await inspect()).draft.data.content, '<p>Texto después de publicar</p>');

    const readOnlySaves = requests.length;
    await second.evaluate(() => {tinymce.get('article-content').mode.set('readonly');tinymce.get('article-content').setContent('<p>Lectura</p>');});
    await second.waitForTimeout(5500); assert.equal(requests.length, readOnlySaves);
    await second.evaluate(() => AdminRichTextEditor.destroy('article-content'));
    await second.waitForTimeout(1200); assert.equal(requests.length, readOnlySaves);
    const discardPage = await context.newPage(); await discardPage.goto('http://content-autosave.test/'); await ready(discardPage);
    await discardPage.locator('[data-autosave-discard]').click();
    await discardPage.getByRole('dialog').getByRole('button', {name:'Cancelar', exact:true}).click();
    assert.equal((await inspect()).draft.data.available, true);
    await discardPage.locator('[data-autosave-discard]').click();
    await discardPage.getByRole('dialog').getByRole('button', {name:'Descartar autoguardado', exact:true}).click();
    await discardPage.locator('[data-autosave-recovery]').waitFor({state:'hidden'});
    assert.equal((await inspect()).draft.data.available, false);
    if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({path:join(process.env.GFRAME_TEST_SCREENSHOTS, 'content-autosave-conflict.png')});
    assert.deepEqual(errors, []);
  } finally { if (release) release(); await context.close(); await browser.close(); rmSync(root, {recursive:true, force:true}); }
});
