const assert = require('node:assert/strict');
const {test} = require('node:test');
const {spawnSync} = require('node:child_process');
const {join} = require('node:path');

test('Alta administrativa valida, incluye CSRF y evita duplicados; funciona en móvil y ambos temas', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const {chromium} = require('playwright');
  const root = join(__dirname, '../..');
  const rendered = spawnSync(process.env.GFRAME_TEST_PHP || 'php', ['-r',
    'require "packages/autoload.php"; session_start(); define("site_url", "https://example.test/"); $_SESSION=["csrfToken"=>"test-token","csrfTimestamp"=>time()]; $data=["data"=>["roles"=>[["role_id"=>2,"name"=>"Usuario"]]]]; include "resources/modules/user-admin/application/app/views/user-admin/user-adminCreate.php"; session_destroy();'
  ], {cwd: root, encoding: 'utf8'});
  assert.equal(rendered.status, 0, rendered.stderr);
  const browser = await chromium.launch({headless: true,
    ...(process.env.GFRAME_TEST_BROWSER !== '1' ? {executablePath: process.env.GFRAME_TEST_BROWSER} : {})});
  try {
    const page = await browser.newPage();
    await page.setContent(rendered.stdout);
    await page.addStyleTag({path: join(root, 'resources/modules/bootstrap/public/css/bootstrap.min.css')});
    await page.addScriptTag({path: join(root, 'resources/modules/jquery/public/jquery.min.js')});
    await page.evaluate(() => {
      window.requests = [];
      window.alertToast = () => {};
      window.ajaxError = () => ({title: 'Error de transporte'});
      $.ajax = options => {
        const deferred = $.Deferred();
        window.requests.push({options, deferred});
        return deferred.promise();
      };
    });
    await page.addScriptTag({path: join(root, 'resources/modules/user-admin/javascript/user-create.js')});
    await page.locator('button[type=submit]').click();
    assert.equal(await page.evaluate(() => window.requests.length), 0);
    await page.locator('#user-create-email').fill('persona@example.test');
    await page.locator('#user-create-role').selectOption('2');
    await page.locator('#user-create-email').press('Enter');
    assert.equal(await page.evaluate(() => window.requests.length), 1);
    const request = await page.evaluate(() => window.requests[0].options);
    assert.equal(request.url, 'https://example.test/ajax/admin/users/create');
    assert.equal(new URLSearchParams(request.data).get('csrfToken'), 'test-token');
    assert.equal(new URLSearchParams(request.data).get('email'), 'persona@example.test');
    await page.evaluate(() => $('#user-create-form').trigger('submit'));
    assert.equal(await page.evaluate(() => window.requests.length), 1);
    await page.evaluate(() => window.requests[0].deferred.resolve({status: 'error', code: 'user_exists', message: 'Ya existe esa cuenta.'}));
    assert.equal(await page.locator('button[type=submit]').isEnabled(), true);
    assert.equal(await page.locator('#user-create-result').textContent(), 'Ya existe esa cuenta.');
    await page.locator('button[type=submit]').click();
    await page.evaluate(() => { window.requests[1].deferred.reject({}, 'error', 'fallo'); });
    assert.equal(await page.locator('button[type=submit]').isEnabled(), true);
    await page.locator('button[type=submit]').click();
    await page.evaluate(() => window.requests[2].deferred.resolve({status: 'success', code: 'user_created_mail_failed', message: 'La cuenta se creó, pero no se pudo enviar el correo.'}));
    assert.equal(await page.locator('#user-create-result').evaluate(el => el.classList.contains('alert-warning')), true);
    assert.equal(await page.locator('button[type=submit]').isDisabled(), true);
    for (const width of [375, 1280]) {
      await page.setViewportSize({width, height: 800});
      for (const theme of ['light', 'dark']) {
        await page.evaluate(value => document.documentElement.dataset.bsTheme = value, theme);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
        assert.equal(await page.locator('h1').textContent(), 'Añadir usuario');
      }
    }
  } finally {
    await browser.close();
  }
});
