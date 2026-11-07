const assert = require('node:assert/strict');
const {test} = require('node:test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {runInNewContext} = require('node:vm');
const root = join(__dirname, '../..');

test('successError distingue navegación gestionada y garantiza texto en errores ordinarios', () => {
  let reloads = 0;
  const $ = () => ({ajaxSend() {}, ajaxComplete() {}});
  const context = {jQuery: $, $, document: {}, window: {location: {href: '/login'}}, location: {reload() {reloads++;}}};
  runInNewContext(readFileSync(join(root, 'resources/modules/frontend-core/public/utils/errors.js'), 'utf8'), context);
  assert.equal(context.successError('Caducó', 'to_reload').handled, true);
  assert.equal(reloads, 1);
  for (const message of [undefined, null, '', '   ']) {
    assert.ok(context.successError(message, 'fail_csrf').title.trim());
  }
});

test('login recupera CSRF sin recarga, conserva los datos y limita los reintentos', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true,
    ...(process.env.GFRAME_TEST_BROWSER !== '1' ? {executablePath: process.env.GFRAME_TEST_BROWSER} : {})});
  try {
    for (const scenario of [
      {code: 'to_reload'}, {code: 'fail_csrf'}, {code: 'fail_csrf', http: 403},
      {code: 'to_reload', repeat: true}, {code: 'fail_csrf', missing: true},
      {code: 'to_reload', unavailable: true}
    ]) {
      const page = await browser.newPage();
      const posts = [], errors = [];
      let refreshes = 0, documents = 0;
      page.on('pageerror', error => errors.push(error.message));
      const markup = readFileSync(join(root, 'resources/modules/auth-ui/application/app/views/auth-ui/authLogin.php'), 'utf8')
        .replace(/<\?=[\s\S]*?\?>/g, php => php.includes('CsrfToken::fields')
          ? '<input type="hidden" name="csrfToken" value="old"><input type="hidden" name="csrfTimestamp" value="1">' : '#');
      await page.route('http://gframe.test/app/**', async route => {
        const request = route.request();
        if (request.method() === 'POST') {
          posts.push(new URLSearchParams(request.postData()));
          const rejected = posts.length === 1 || scenario.repeat;
          await route.fulfill({status: rejected ? (scenario.http || 200) : 200, contentType: 'application/json', body: JSON.stringify(rejected
            ? {status: 'unauthorized', code: scenario.code, message: 'Formulario caducado'}
            : {status: 'error', code: 'invalid_user', message: 'Credenciales incorrectas'})});
        } else if (request.resourceType() === 'document') {
          documents++;
          await route.fulfill({contentType: 'text/html', body: markup});
        } else {
          refreshes++;
          await new Promise(resolve => setTimeout(resolve, 80));
          await route.fulfill({status: scenario.unavailable ? 503 : 200, contentType: 'text/html', body: scenario.missing ? '<h1>Error</h1>'
            : '<form id="login"><input name="csrfToken" value="fresh"><input name="csrfTimestamp" value="2"></form><script>window.executedRefreshScript=true</script>'});
        }
      });
      await page.goto('http://gframe.test/app/login?rd=admin%2Fitems');
      await page.addScriptTag({path: join(root, 'resources/modules/jquery/public/jquery.min.js')});
      await page.evaluate(() => {
        window.site_url = 'http://gframe.test/app/';
        window.notices = [];
        window.validationFeedback = window.showPassword = window.showSpinner = () => {};
        window.alertToast = options => window.notices.push(options);
        window.swalAlert = options => {window.notices.push(options); return Promise.resolve({isConfirmed: false});};
      });
      for (const script of ['frontend-core/public/utils/errors.js', 'auth-ui/javascript/auth-request.js', 'auth-ui/javascript/AuthLogin.js']) {
        await page.addScriptTag({path: join(root, 'resources/modules', script)});
      }
      await page.locator('#login_email').fill('nombre+evento@example.info');
      await page.locator('#login_password').fill('  Clave+á&=123  ');
      await page.locator('#login_password').press('Enter');
      await page.locator('#login_password').press('Enter');
      await page.waitForFunction(() => window.notices.length > 0);
      assert.equal(refreshes, 1, JSON.stringify(scenario));
      assert.equal(posts.length, scenario.missing || scenario.unavailable ? 1 : 2);
      assert.equal(documents, 1, 'no recarga ni reemplaza el documento');
      assert.equal(await page.locator('#login_password').inputValue(), '  Clave+á&=123  ');
      assert.equal(await page.locator('#login_email').inputValue(), 'nombre+evento@example.info');
      assert.equal(await page.evaluate(() => window.executedRefreshScript), undefined);
      assert.equal(await page.evaluate(() => window.notices.every(item => typeof item.title === 'string' && item.title.trim().length > 0)), true);
      if (posts.length === 2) {
        assert.equal(posts[1].get('csrfToken'), 'fresh');
        assert.equal(posts[1].get('csrfTimestamp'), '2');
        for (const key of ['login_password', 'login_email', 'rd', 'middle_name']) assert.equal(posts[1].get(key), posts[0].get(key));
      }
      assert.deepEqual(errors, []);
      // El bloqueo se libera también cuando la renovación falla.
      await page.locator('#login_password').press('Enter');
      await page.waitForFunction(() => window.notices.length > 1);
      await page.close();
    }
  } finally {await browser.close();}
});
