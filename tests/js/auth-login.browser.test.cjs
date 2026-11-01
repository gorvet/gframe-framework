const assert = require('node:assert/strict');
const {test} = require('node:test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');

test('El login original admite clic y Enter, valida y evita envíos duplicados', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true,
    ...(process.env.GFRAME_TEST_BROWSER !== '1' ? {executablePath: process.env.GFRAME_TEST_BROWSER} : {}),
    ...(process.env.GFRAME_TEST_BROWSER_CHANNEL ? {channel: process.env.GFRAME_TEST_BROWSER_CHANNEL} : {})});
  try {
    const page = await browser.newPage();
    const root = join(__dirname, '../..');
    const markup = readFileSync(join(root, 'resources/modules/auth-ui/application/app/views/auth-ui/authLogin.php'), 'utf8')
      .replace(/<\?=[\s\S]*?\?>/g, '#');
    await page.setContent(markup);
    await page.addScriptTag({path: join(root, 'resources/modules/jquery/public/jquery.min.js')});
    await page.evaluate(() => {
      window.requests = [];
      window.feedback = 0;
      window.site_url = '/';
      window.validationFeedback = () => {window.feedback++;};
      window.showPassword = () => {};
      window.alertToast = () => {};
      window.ajaxError = () => ({title: ''});
      $.ajax = options => {window.requests.push(options);};
    });
    await page.addScriptTag({path: join(root, 'resources/modules/auth-ui/javascript/AuthLogin.js')});
    await page.locator('#login_password').fill('Password-123');
    await page.locator('#login_password').press('Enter');
    assert.equal(await page.evaluate(() => window.requests.length), 0);
    assert.equal(await page.evaluate(() => window.feedback), 1);
    assert.equal(await page.locator('#login').evaluate(el => el.classList.contains('was-validated')), true);

    await page.locator('#login_email').fill('nombre+evento@example.info');
    await page.locator('#submit_login').click();
    assert.equal(await page.evaluate(() => window.requests.length), 1);
    await page.locator('#login_password').press('Enter');
    assert.equal(await page.evaluate(() => window.requests.length), 1);
    await page.evaluate(() => {
      window.requests[0].error({}, 'error', '');
      window.requests[0].complete();
    });
    await page.locator('#login_password').press('Enter');
    assert.equal(await page.evaluate(() => window.requests.length), 2);
    assert.equal(await page.evaluate(() => window.requests[1].url), '/ajax/login');
    const data = await page.evaluate(() => window.requests[1].data);
    assert.equal(new URLSearchParams(data).get('login_email'), 'nombre+evento@example.info');
    assert.equal(new URLSearchParams(data).get('login_password'), 'Password-123');
  } finally {
    await browser.close();
  }
});

test('Acceso, registro y recuperación aceptan alias + y dominios largos sin aceptar correos malformados', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true,
    ...(process.env.GFRAME_TEST_BROWSER !== '1' ? {executablePath: process.env.GFRAME_TEST_BROWSER} : {})});
  try {
    const page = await browser.newPage();
    for (const [view, id] of [['authLogin', 'login_email'], ['authRegister', 'register_email'], ['authRecovery', 'recovery_email']]) {
      const markup = readFileSync(join(__dirname, '../../resources/modules/auth-ui/application/app/views/auth-ui/' + view + '.php'), 'utf8')
        .replace(/<\?[\s\S]*?\?>/g, '');
      await page.setContent(markup);
      const input = page.locator('#' + id);
      for (const email of ['nombre+evento@gmail.com', 'nombre@example.info', 'nombre+evento@example.info', 'Nombre@Example.technology']) {
        await input.fill(email);
        assert.equal(await input.evaluate(el => el.checkValidity()), true, view + ': ' + email);
      }
      for (const email of ['', 'sin-arroba', 'nombre@@example.info', 'nombre con espacio@example.info', 'nombre@']) {
        await input.fill(email);
        assert.equal(await input.evaluate(el => el.checkValidity()), false, view + ': ' + email);
      }
    }
  } finally { await browser.close(); }
});
