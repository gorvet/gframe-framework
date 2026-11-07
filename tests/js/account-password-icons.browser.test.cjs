const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const {mkdtempSync, rmSync, readFileSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join} = require('node:path');
const net = require('node:net');

test('Cuenta: contraseña única, medidor, mostrar/ocultar; gicon Bootstrap y sangría', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 45000}, async () => {
  const root = mkdtempSync(join(tmpdir(), 'gframe-account-ui-'));
  const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
  const fixture = join(__dirname, '../fixtures/account-ui.php');
  const env = {...process.env, GFRAME_ACCOUNT_TEST_ROOT: root};
  const modules = JSON.parse(execFileSync(php, [fixture], {env, windowsHide: true, encoding: 'utf8'}));
  assert.ok(modules.includes('bootstrap-icons'), 'Publicar gframe-icons incluye Bootstrap Icons.');
  const listener = net.createServer();
  await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
  const port = listener.address().port; await new Promise(resolve => listener.close(resolve));
  const server = spawn(php, ['-S', '127.0.0.1:' + port, fixture], {env, windowsHide: true});
  let logs = ''; server.stderr.on('data', value => logs += value);
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  try {
    const page = await browser.newPage({viewport: {width: 1440, height: 900}});
    page.setDefaultTimeout(7000);
    const base = 'http://127.0.0.1:' + port;
    for (let i = 0; i < 40; i++) {
      try { if ((await fetch(base, {signal: AbortSignal.timeout(1000)})).ok) break; } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    await page.goto(base);
    const form = page.locator('#account-password-form');
    assert.equal(await form.locator('input[type=password]').count(), 2);
    assert.equal(await form.locator('[name=new_password_confirmation]').count(), 0);
    assert.equal(await form.locator('#current_password').getAttribute('autocomplete'), 'off');
    await form.locator('#new_password').fill('Nueva-clave-123');
    assert.equal(await form.locator('.passwordMeter').isVisible(), true);
    await form.locator('[aria-controls=new_password]').click();
    assert.equal(await form.locator('#new_password').getAttribute('type'), 'text');
    await form.locator('[aria-controls=current_password]').click();
    assert.equal(await form.locator('#current_password').getAttribute('type'), 'text');
    await form.locator('#current_password').fill('incorrecta');
    await form.locator('[type=submit]').click();
    await page.waitForFunction(() => window.lastToast?.icon === 'error');
    const payloads = []; page.on('request', req => { if (req.method() === 'POST') payloads.push(new URLSearchParams(req.postData())); });
    await form.locator('#current_password').fill('Clave-actual-123');
    const saved = page.waitForResponse(response => response.url().endsWith('/password') && response.request().method() === 'POST');
    await form.locator('[type=submit]').click();
    const responseText = await (await saved).text();
    assert.ok(responseText.startsWith('{'), responseText.slice(0, 1500));
    const response = JSON.parse(responseText);
    assert.equal(response.code, 'password_updated'); assert.equal(response.test_password_changed, true);
    assert.equal(payloads[0].has('new_password_confirmation'), false);
    assert.equal(payloads[0].get('csrfToken'), 'account-test-token');
    await page.waitForFunction(() => document.querySelector('#new_password').value === '');
    assert.equal(await form.locator('#current_password').getAttribute('type'), 'password');
    assert.equal(await form.locator('.passwordMeter').isVisible(), false);
    await page.evaluate(() => document.fonts.ready);
    const mapped = await page.locator('.gicon-image').evaluate(el => ({family: getComputedStyle(el, '::before').fontFamily, content: getComputedStyle(el, '::before').content}));
    assert.match(mapped.family, /bootstrap-icons/); assert.notEqual(mapped.content, 'none');
    const css = readFileSync(join(__dirname, '../../resources/modules/gframe-icons/public/style.css'), 'utf8');
    const compat = readFileSync(join(__dirname, '../../resources/modules/gframe-icons/public/bootstrap-icons-compat.css'), 'utf8');
    for (const match of css.matchAll(/\.(gicon-[\w-]+):before/g)) assert.ok(compat.includes('.' + match[1] + '::before'), match[1]);
    const positions = await page.locator('.sidebar-nav').evaluate(el => ({heading: el.querySelector('.nav-heading').getBoundingClientRect().left, icon: el.querySelector('i').getBoundingClientRect().left}));
    assert.equal(positions.icon - positions.heading, 10);
    const screenshots = process.env.GFRAME_TEST_SCREENSHOTS;
    if (screenshots) await page.screenshot({path: join(screenshots, 'account-password-icons-desktop.png')});
    await page.setViewportSize({width: 390, height: 844});
    await page.waitForTimeout(350);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await form.locator('#new_password').fill('Nueva-clave-123');
    if (screenshots) await page.screenshot({path: join(screenshots, 'account-password-mobile.png')});
  } catch (error) {error.message += '\nPHP: ' + logs.slice(-2000); throw error;}
  finally {await browser.close(); server.kill(); await new Promise(resolve => server.once('exit', resolve)); rmSync(root, {recursive: true, force: true});}
});
