const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const {mkdtempSync, readFileSync, rmSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join} = require('node:path');

test('Guardado versionado real y presencia: conflicto, tokens y lifecycle', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 60000}, async () => {
  const root = mkdtempSync(join(tmpdir(), 'gframe-content-browser-'));
  const framework = join(__dirname, '../..');
  const fixture = join(__dirname, '../fixtures/content-editing.php');
  const dispatch = (url, method, body) => new Promise((resolve, reject) => {
    const input = Buffer.from(JSON.stringify({url, method, body, fixture})).toString('base64');
    const code = '$r=json_decode(base64_decode($argv[1]),true);$_SERVER["REQUEST_URI"]=parse_url($r["url"],PHP_URL_PATH);$_SERVER["REQUEST_METHOD"]=$r["method"];parse_str($r["body"],$_POST);$_REQUEST=$_POST;include $r["fixture"];';
    const child = spawn(process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe', ['-r', code, input], {env: {...process.env, GFRAME_CONTENT_TEST_ROOT: root}, windowsHide: true});
    let output = '', errors = ''; child.stdout.on('data', d => output += d); child.stderr.on('data', d => errors += d);
    child.on('error', reject); child.on('exit', code => code ? reject(new Error(errors)) : resolve(output));
  });
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  const context = await browser.newContext();
  const requests = [], errors = [];
  let hold = null, held = null, release = null, heldToken = null;
  await context.route('http://content-editing.test/**', async route => {
    const request = route.request(), body = request.postData() || '';
    if (request.method() === 'POST') {
      requests.push({url: request.url(), data: Object.fromEntries(new URLSearchParams(body))});
      if (hold && !heldToken && request.url().endsWith('/presence')) {
        heldToken = Object.fromEntries(new URLSearchParams(body)).editor_token;
        held(); await hold;
      }
    }
    let output = await dispatch(request.url(), request.method(), body).catch(error => {
      errors.push(error.message); console.error(error.message);
      return JSON.stringify({status: 'error', code: 'fixture_failed'});
    });
    if (heldToken && new URLSearchParams(body).get('editor_token') === heldToken) output = JSON.stringify({...JSON.parse(output), message: 'stale_response'});
    try { await route.fulfill({contentType: request.method() === 'POST' ? 'application/json' : 'text/html', body: output}); }
    catch (error) { if (!/closed|cancel|disposed/i.test(error.message)) errors.push(error.message); }
  });
  async function page() {
    const p = await context.newPage(); p.on('pageerror', error => errors.push(error.message));
    await p.goto('http://content-editing.test/');
    await p.addScriptTag({content: readFileSync(join(framework, 'resources/modules/jquery/public/jquery.min.js'), 'utf8')});
    await p.addScriptTag({content: readFileSync(join(framework, 'resources/modules/content-editing/public/content-editing.js'), 'utf8')});
    await p.waitForFunction(() => !!window.ContentEditing);
    return p;
  }
  try {
    const first = await page();
    assert.equal(await first.evaluate(() => ContentEditing.mount(null)), null);
    assert.equal(await first.evaluate(() => ContentEditing.mount(document.createElement('div'))), null);
    await first.waitForTimeout(500);
    const second = await page();
    await second.locator('[data-content-presence-status]').waitFor({state: 'visible'});
    assert.match(await second.locator('[data-content-presence-status]').textContent(), /otra sesión/);
    const save = p => p.evaluate(async () => {
      const data = new URLSearchParams(new FormData(document.querySelector('#article')));
      data.set('csrfToken', 'fixture-token'); data.set('csrfTimestamp', '100');
      return (await fetch('/save', {method: 'POST', body: data})).json();
    });
    await first.locator('#content').fill('Primero');
    const saved = await save(first); assert.equal(saved.status, 'success');
    await first.evaluate(revision => ContentEditing.mount(document.querySelector('[data-content-editing]')).confirmSaved(revision), saved.data.revision);
    assert.equal(await first.locator('[name="content_revision"]').inputValue(), saved.data.revision);
    await second.locator('#content').fill('Conservar este texto');
    const conflict = await save(second); assert.equal(conflict.code, 'content_conflict');
    assert.equal(await second.locator('#content').inputValue(), 'Conservar este texto');
    assert.equal(await second.locator('[name="content_revision"]').inputValue(), 'r1');
    const invalid = JSON.parse(await dispatch('http://content-editing.test/presence', 'POST', 'editor_token=' + 'a'.repeat(32)));
    assert.equal(invalid.code, 'fail_csrf');

    await first.bringToFront();
    const hiddenCount = requests.length;
    await first.evaluate(() => { Object.defineProperty(document, 'hidden', {configurable: true, value: true}); document.dispatchEvent(new Event('visibilitychange')); });
    await first.waitForTimeout(100); assert.equal(requests.length, hiddenCount);
    await first.evaluate(() => { Object.defineProperty(document, 'hidden', {configurable: true, value: false}); document.dispatchEvent(new Event('visibilitychange')); });
    await first.waitForTimeout(300);

    await first.bringToFront();
    let started = new Promise(resolve => held = resolve); hold = new Promise(resolve => release = resolve);
    await first.evaluate(() => {
      const root = document.querySelector('[data-content-editing]');
      window.oldEditing = ContentEditing.mount(root); oldEditing.destroy(); oldEditing.destroy();
      ContentEditing.mount(root);
    });
    await started;
    const before = requests.length;
    await first.evaluate(() => { document.dispatchEvent(new Event('visibilitychange')); document.dispatchEvent(new Event('visibilitychange')); });
    await first.waitForTimeout(100); assert.equal(requests.length, before);
    await first.evaluate(() => {
      const root = document.querySelector('[data-content-editing]');
      window.pendingEditing = ContentEditing.mount(root); pendingEditing.destroy();
      window.currentEditing = ContentEditing.mount(root);
    });
    await first.waitForTimeout(500);
    const destroyedToken = requests.filter(r => r.url.endsWith('/presence')).at(-1).data.editor_token;
    await first.evaluate(() => { const status = document.querySelector('[data-content-presence-status]'); status.textContent = 'new_instance'; });
    release(); hold = null;
    await first.waitForTimeout(300);
    assert.doesNotMatch(await first.locator('[data-content-presence-status]').textContent(), /stale_response/);
    await first.evaluate(() => { pendingEditing.confirmSaved('wrong-revision'); currentEditing.destroy(); pendingEditing.destroy(); });
    assert.equal(await first.locator('[name="content_revision"]').inputValue(), saved.data.revision);
    const destroyedRequests = requests.filter(r => r.data.editor_token === destroyedToken).length;
    await first.waitForTimeout(31000);
    assert.equal(requests.filter(r => r.data.editor_token === destroyedToken).length, destroyedRequests);
    assert.ok(requests.filter(r => r.url.endsWith('/presence')).every(r => r.data.csrfToken === 'fixture-token' && /^[a-f0-9]{32}$/.test(r.data.editor_token)));
    assert.deepEqual(errors, []);
  } catch (error) { console.error(error.stack); throw error; }
  finally { if (release) release(); await context.unrouteAll({behavior: 'ignoreErrors'}); await context.close(); await browser.close(); rmSync(root, {recursive: true, force: true}); }
});
