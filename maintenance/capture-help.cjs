// Capturas de interfaces reales en una aplicación temporal; no utiliza producción.
const {mkdtempSync, mkdirSync, cpSync, writeFileSync, readFileSync, readdirSync, rmSync, realpathSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join, resolve, sep} = require('node:path');
const {spawn, execFileSync, execFile} = require('node:child_process');
const {promisify} = require('node:util');
const net = require('node:net');
const {chromium} = require('playwright');
const source = resolve(__dirname, '..');
const output = resolve(process.argv[2]);
const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
const temporary = mkdtempSync(join(tmpdir(), 'gframe-help-capture-'));
const servers = [];
let browser;
async function serve(root, router) {
    const port = await new Promise(done => { const s = net.createServer(); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => done(p)); }); });
    const server = spawn(php, ['-S', '127.0.0.1:' + port, '-t', root, ...(router ? [router] : [])], {windowsHide: true, stdio: 'ignore'});
    servers.push(server);
    return 'http://127.0.0.1:' + port + '/';
}
async function context(base) {
    const ctx = await browser.newContext({viewport: {width: 1440, height: 1000}, timezoneId: 'UTC'});
    await ctx.route(base + '**', async route => {
        const req = route.request();
        const input = Buffer.from(JSON.stringify({url: req.url(), method: req.method(), body: req.postData() || '', headers: req.headers()})).toString('base64');
        try {
            const {stdout} = await promisify(execFile)(php, [join(source, 'tests/fixtures/editorial-integration/http-bridge.php'), input], {windowsHide: true, maxBuffer: 12 * 1024 * 1024});
            const response = JSON.parse(stdout);
            await route.fulfill({...response, body: Buffer.from(response.body, 'base64')});
        } catch (error) { await route.abort(); throw error; }
    });
    return ctx;
}
(async () => {
    try {
        mkdirSync(output, {recursive: true});
        browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
        const installer = join(temporary, 'installer');
        cpSync(join(source, 'resources/skeleton'), installer, {recursive: true});
        mkdirSync(join(installer, 'packages'));
        writeFileSync(join(installer, 'packages/autoload.php'), '<?php return require ' + JSON.stringify(join(source, 'packages/autoload.php').replaceAll('\\', '/')) + ';');
        const installerBase = await serve(installer);
        const installContext = await context(installerBase);
        const wizard = await installContext.newPage();
        await wizard.goto(installerBase + 'install.php');
        await wizard.locator('#app_name').fill('Estudio Nexo · Revista digital');
        await wizard.locator('.shell').screenshot({path: join(output, 'instalacion.png')});
        await installContext.close();

        const app = join(temporary, 'application');
        mkdirSync(app);
        // Reserve the port before installation, then serve the generated application.
        const port = await new Promise(done => { const s = net.createServer(); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => done(p)); }); });
        const base = 'http://127.0.0.1:' + port + '/';
        execFileSync(php, [join(source, 'tests/fixtures/editorial-integration/setup.php'), app, base], {windowsHide: true});
        // Customize only the copied application's labels, never framework/runtime originals.
        const labelViews = directory => {
            for (const entry of readdirSync(directory, {withFileTypes: true})) {
                const file = join(directory, entry.name);
                if (entry.isDirectory()) labelViews(file);
                else if (entry.name.endsWith('.php')) {
                    const old = readFileSync(file, 'utf8');
                    const text = old.replaceAll('Prueba editorial', 'Estudio Nexo').replaceAll('Artículo de prueba', 'Cómo publicar en equipo sin perder el hilo');
                    if (text !== old) writeFileSync(file, text);
                }
            }
        };
        labelViews(join(app, 'app/views'));
        const envPath = join(app, '.env');
        writeFileSync(envPath, readFileSync(envPath, 'utf8').replaceAll('Prueba editorial', 'Estudio Nexo'));
        const seed = 'require ' + JSON.stringify(join(app, 'core/Load.php').replaceAll('\\', '/')) + ';'
            + '$s=new GFrame\\Media\\MediaLibraryService(new GFrame\\Media\\MediaModel(),new GFrame\\Media\\MediaStorage(' + JSON.stringify(join(app, 'public').replaceAll('\\', '/')) + '));'
            + '$s->registerLocalFile(' + JSON.stringify(join(output, 'equipo-editorial.png').replaceAll('\\', '/')) + ',"Equipo editorial - portada.png");'
            + '$s->registerLocalFile(' + JSON.stringify(join(source, 'resources/skeleton/public/img/logo.png').replaceAll('\\', '/')) + ',"GFrame - recursos de marca.png");';
        execFileSync(php, ['-r', seed], {windowsHide: true});
        servers.push(spawn(php, ['-S', '127.0.0.1:' + port, '-t', app, join(app, 'router.php')], {windowsHide: true, stdio: 'ignore'}));
        const ctx = await context(base);
        const page = await ctx.newPage();
        await page.goto(base + 'login');
        const tokens = await page.locator('#login').evaluate(f => Object.fromEntries(new FormData(f)));
        const login = await page.evaluate(async ({base, tokens}) => (await fetch(base + 'ajax/login', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: new URLSearchParams({...tokens, login_email: 'integration@example.com', login_password: 'Fixture-Only-123!'})})).json(), {base, tokens});
        if (login.status !== 'success') throw new Error('Login temporal fallido.');
        await page.goto(base + 'admin/media');
        await page.locator('h1').first().waitFor();
        await page.setViewportSize({width: 980, height: 900});
        await page.waitForFunction(() => !document.body.classList.contains('drawer-open'));
        await page.locator('.section.dashboard').screenshot({path: join(output, 'multimedia.png'), animations: 'disabled'});
        await page.setViewportSize({width: 1440, height: 1000});
        await page.goto(base + 'editorial');
        await page.waitForFunction(() => window.tinymce?.get('article-content')?.initialized);
        const article = '<h2>Una redacción más clara, del borrador a la publicación</h2>'
            + '<p><strong>Una buena publicación empieza mucho antes de pulsar Guardar.</strong> Empieza con una idea concreta, una persona que sabe qué necesita el lector y un equipo que puede revisar el contenido sin pisarse el trabajo. En Estudio Nexo usamos este proceso para convertir notas dispersas en artículos útiles, con una voz reconocible y una salida cuidada.</p>'
            + '<p>Esta semana preparamos nuestra guía para organizar un calendario editorial pequeño: tres personas, dos publicaciones semanales y tiempo suficiente para comprobar lo que contamos. El objetivo es sencillo: dedicar más energía a escribir y menos a buscar cuál era la última versión.</p>'
            + '<figure><img src="' + base + 'public/equipo-editorial.png" alt="Mesa de trabajo de un equipo editorial, con portátiles, cuaderno y materiales de planificación" width="1100" height="619"></figure>'
            + '<h2>1. Antes de escribir, acuerda la intención</h2><p>Define qué pregunta responde el artículo y qué debería poder hacer el lector al terminar. Una introducción breve, un ejemplo reconocible y un cierre práctico suelen ayudar más que una lista de promesas. Si una sección no aporta a esa intención, revísala antes de añadir otra.</p>'
            + '<ul><li><strong>Audiencia:</strong> equipos que publican contenidos y necesitan mantener un criterio común.</li><li><strong>Resultado:</strong> salir con un proceso repetible, desde el primer borrador hasta la revisión final.</li><li><strong>Tono:</strong> cercano, preciso y sin términos que compliquen una idea sencilla.</li></ul>'
            + '<h2>2. Revisa sin perder lo que ya funciona</h2><p>El primer borrador sirve para pensar; el segundo, para que otra persona pueda entenderlo. Revisa los títulos, elimina repeticiones y conserva los ejemplos que explican una decisión. Antes de una reescritura amplia, confirma el guardado para que el historial mantenga una referencia clara.</p>'
            + '<blockquote>El mejor flujo editorial no añade pasos por costumbre: hace visible qué está preparado, qué necesita revisión y quién toma la siguiente decisión.</blockquote>'
            + '<h2>3. Mira el contenido en su contexto</h2><p>La vista previa permite comprobar el ritmo real de lectura: tamaño de los títulos, relación entre texto e imágenes y longitud de cada sección. El autoguardado privado ayuda a continuar el trabajo, pero no sustituye la confirmación de un guardado ni convierte un borrador en una publicación.</p>'
            + '<ol><li>Lee el inicio como si fuera tu primera visita.</li><li>Comprueba el texto alternativo y la intención de cada imagen.</li><li>Revisa la versión móvil y los enlaces antes de publicar.</li><li>Confirma quién aprueba la salida y cuándo debe estar disponible.</li></ol>'
            + '<h2>Una lista corta para el cierre</h2><p>Antes de programar la publicación, dejamos anotados el título definitivo, la imagen de portada y la persona responsable de responder a las primeras preguntas. Si aparece una mejora después, volvemos al contenido con una intención concreta; no cambiamos todo por empezar otra vez.</p>'
            + '<p><strong>La siguiente entrega:</strong> cómo convertir las preguntas frecuentes de tus lectores en un calendario que puedas sostener durante todo el mes.</p>';
        cpSync(join(output, 'equipo-editorial.png'), join(app, 'public/equipo-editorial.png'));
        await page.evaluate(html => tinymce.get('article-content').setContent(html), article);
        await page.locator('[data-autosave-status]').filter({hasText: 'Autoguardado privado actualizado'}).waitFor({timeout: 20000});
        await page.screenshot({path: join(output, 'editor.png'), fullPage: true});
        await page.goto(base + 'preview');
        await page.locator('#articleBody').filter({hasText: 'Una redacción más clara'}).waitFor();
        await page.locator('main').screenshot({path: join(output, 'vista-previa.png')});
        await ctx.close();
        process.stdout.write('Cuatro capturas reales generadas en ' + output + '\n');
    } finally {
        if (browser) await browser.close();
        for (const server of servers) if (server.exitCode === null) { const ended = new Promise(done => server.once('exit', done)); server.kill(); await ended; }
        const path = realpathSync(temporary);
        if (!path.startsWith(realpathSync(tmpdir()) + sep) || !/^gframe-help-capture-/.test(path.split(sep).pop())) throw new Error('Ruta temporal no válida.');
        rmSync(path, {recursive: true, force: true});
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
