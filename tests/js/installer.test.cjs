const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn, execFileSync } = require('node:child_process');
let chromium;
try { ({ chromium } = require('playwright')); } catch { /* Prueba de navegador opcional. */ }
const net = require('node:net');

test('El catálogo oculta las dependencias al seleccionar módulos, sin red', { skip: !chromium || !process.env.GFRAME_TEST_BROWSER }, async () => {
    const root = path.resolve(__dirname, '../..');
    const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'gframe-options-dom-'));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.GFRAME_TEST_BROWSER });
    try {
        fs.cpSync(path.join(root, 'resources/skeleton'), temporary, { recursive: true });
        fs.mkdirSync(path.join(temporary, 'packages'));
        fs.writeFileSync(path.join(temporary, 'packages/autoload.php'), `<?php require ${JSON.stringify(path.join(root, 'packages/autoload.php').replaceAll('\\', '/'))};`);
        const entry = path.join(temporary, 'install.php').replaceAll('\\', '/');
        const html = execFileSync(process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe', ['-r', `$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/install.php'; require ${JSON.stringify(entry)};`], { encoding: 'utf8' });
        const page = await browser.newPage();
        await page.setContent(html.replace(/<link\b[^>]*>/gi, '').replace(/<script\b[^>]*\bsrc=[^>]*>[\s\S]*?<\/script>/gi, ''));
        await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'resources/skeleton/public/js/install/install.js'), 'utf8') });
        const input = name => page.locator(`[name="modules[]"][value="${name}"]`);
        const hidden = name => input(name).evaluate(element => element.closest('label').hidden);
        const set = async (name, checked) => { await input(name).evaluate((element, value) => { element.checked = value; element.dispatchEvent(new Event('change')); }, checked); };
        await set('notification-campaigns', true);
        for (const name of ['notifications', 'cron-runner', 'flatpickr']) {
            assert.equal(await hidden(name), true);
            assert.equal(await input(name).isDisabled(), true);
        }
        await set('notification-campaigns', false);
        for (const name of ['notifications', 'cron-runner', 'flatpickr']) assert.equal(await hidden(name), false);
        await set('notifications', true);
        assert.equal(await hidden('cron-runner'), true);
        assert.equal(await hidden('flatpickr'), false);
        await page.locator('#profile').selectOption('static');
        assert.equal(await hidden('notification-campaigns'), true);
        assert.equal(await hidden('rich-text-editor'), true);
        assert.equal(await hidden('markdown'), false);
        assert.equal(await hidden('lexical-search'), false);
        assert.equal(await input('notifications').isChecked(), false);
        assert.equal(await hidden('aos'), false);
    } finally {
        await browser.close();
        fs.rmSync(temporary, { recursive: true, force: true });
    }
});

test('El asistente instala los cuatro perfiles con cuenta condicional y opciones separadas', { skip: !chromium || !process.env.GFRAME_TEST_BROWSER }, async () => {
    const root = path.resolve(__dirname, '../..');
    const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'gframe-wizard-'));
    const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
    const browser = await chromium.launch({ headless: true, ...(process.env.GFRAME_TEST_BROWSER ? { executablePath: process.env.GFRAME_TEST_BROWSER } : {}) });
    try {
        for (const profile of ['static', 'managed', 'intranet', 'saas']) {
            const project = path.join(temporary, profile);
            fs.cpSync(path.join(root, 'resources/skeleton'), project, { recursive: true });
            fs.mkdirSync(path.join(project, 'packages'));
            fs.writeFileSync(path.join(project, 'packages/autoload.php'), `<?php require ${JSON.stringify(path.join(root, 'packages/autoload.php').replaceAll('\\', '/'))};`);
            const reservation = net.createServer();
            await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
            const port = reservation.address().port;
            await new Promise(resolve => reservation.close(resolve));
            const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', project], { stdio: 'ignore', windowsHide: true });
            const context = await browser.newContext();
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            try {
                const url = `http://127.0.0.1:${port}/install.php`;
                for (let attempt = 0; attempt < 40; attempt++) {
                    try { await page.goto(url); break; } catch (error) {
                        if (attempt === 39) throw error;
                        await new Promise(resolve => setTimeout(resolve, 100));
                    }
                }
                assert.equal(await page.locator('[data-step]:visible').count(), 1);
                assert.equal(await page.locator('[data-step="Aplicación"]').isVisible(), true);
                assert.equal(await page.locator('#app_name').isVisible(), true);
                assert.equal(await page.locator('#installer-submit').isVisible(), false);
                assert.equal(await page.locator('#installer-next').textContent(), 'Continuar');
                assert.equal(await page.locator('#profile').inputValue(), 'managed');
                await page.locator('#app_name').fill(`Prueba ${profile}`);
                await page.locator('#profile').selectOption(profile);
                assert.equal(await page.locator('#installer-account').isVisible(), profile !== 'static');
                assert.equal(await page.locator('[name="password_confirmation"]').count(), 0);
                if (profile !== 'static') {
                    await page.locator('#email').fill(`${profile}@example.test`);
                    await page.locator('#password').fill('Password-123');
                    await page.locator('#installer-show-password').click();
                    assert.equal(await page.locator('#password').getAttribute('type'), 'text');
                    await page.locator('#installer-show-password').click();
                }
                assert.equal(await page.locator('.install-logo').evaluate(image => image.complete && image.naturalWidth > 0), true);
                if (profile === 'managed' && process.env.GFRAME_TEST_SCREENSHOTS) {
                    await page.screenshot({ path: path.join(process.env.GFRAME_TEST_SCREENSHOTS, 'installer-desktop.png'), fullPage: true });
                    await page.setViewportSize({ width: 390, height: 844 });
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    await page.screenshot({ path: path.join(process.env.GFRAME_TEST_SCREENSHOTS, 'installer-mobile.png'), fullPage: true });
                    await page.setViewportSize({ width: 1280, height: 720 });
                }
                assert.equal(await page.locator('.installer-progress,.header').count(), 0);
                assert.equal(await page.locator('[name="timezone"],[name="seo_enabled"],[name="seo_sitemap"],[name="seo_robots"],[name="seo_llms"],[name="database_auto_create"]').count(), 0);
                await page.locator('#installer-next').click();
                if (profile !== 'static') {
                    if (profile === 'managed') {
                        await page.locator('#database_name').fill('gframe_test');
                        await page.locator('#database_user').fill('test');
                        await page.route('**/install.php', route => route.request().method() === 'POST' ? route.fulfill({ json: { status: 'error', code: 'database_connection_failed', message: 'Comprueba el servidor de pruebas.' } }) : route.continue());
                        await page.locator('#installer-next').click();
                        await page.locator('.swal2-popup').waitFor({ state: 'visible' });
                        assert.equal(await page.locator('[data-step="Base de datos"]').isVisible(), true);
                        if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.GFRAME_TEST_SCREENSHOTS, 'installer-error.png'), fullPage: true });
                        await page.locator('.swal2-confirm').click();
                        await page.locator('.swal2-popup').waitFor({ state: 'hidden' });
                        await page.unroute('**/install.php');
                        assert.equal(await page.locator('#database_name').inputValue(), 'gframe_test');
                        if (process.env.GFRAME_TEST_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.GFRAME_TEST_SCREENSHOTS, 'installer-database.png'), fullPage: true });
                    }
                    await page.locator('#database_driver').selectOption('sqlite');
                    assert.equal(await page.locator('#database_name').isDisabled(), true);
                    await page.locator('#installer-next').click();
                    await page.locator('[data-step="Módulos"]').waitFor({ state: 'visible' });
                } else {
                    assert.equal(await page.locator('[name="modules[]"][value="notification-campaigns"]').isDisabled(), true);
                }
                assert.equal(await page.locator('[data-step="Módulos"]').isVisible(), true);
                if (profile === 'static') assert.equal(await page.locator('[name="modules[]"][value="rich-text-editor"]').isDisabled(), true);
                assert.equal(await page.locator('[name="modules[]"][value="gfselect"]').count(), 0);
                await page.locator('#installer-next').click();
                assert.equal(await page.locator('[data-step="Resumen"]').isVisible(), true);
                assert.equal(fs.existsSync(path.join(project, 'storage/gframe-installed.json')), false);
                assert.equal(fs.existsSync(path.join(project, 'public/vendors/external/sweetalert2/sweetalert2.all.min.js')), true);
                assert.equal(fs.existsSync(path.join(project, 'app/controllers/auth-ui')), false);
                const summary = await page.locator('#installer-summary').textContent();
                assert.equal(summary.includes('Password-123'), false);
                await Promise.all([page.waitForNavigation(), page.locator('#installer-submit').click()]);
                assert.match(await page.locator('.result').textContent(), /instalada correctamente/);
                const installed = JSON.parse(fs.readFileSync(path.join(project, 'storage/gframe-installed.json'), 'utf8'));
                assert.equal(installed.profile, profile);
                assert.ok(installed.modules.includes('gfselect'));
                assert.ok(installed.modules.includes('gf-table'));
                if (profile !== 'static') assert.ok(installed.modules.includes('auth-ui'));
                assert.deepEqual(errors, []);
            } finally {
                await context.close();
                server.kill();
                await new Promise(resolve => { if (server.exitCode !== null) resolve(); else server.once('exit', resolve); });
            }
        }
    } finally {
        await browser.close();
        fs.rmSync(temporary, { recursive: true, force: true });
    }
});
