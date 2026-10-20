// Capturas de interfaces reales en una aplicación temporal; no utiliza producción.
const {mkdtempSync, mkdirSync, cpSync, writeFileSync, readFileSync, readdirSync, rmSync, realpathSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join, resolve, sep} = require('node:path');
const {spawn, execFileSync, execFile} = require('node:child_process');
const {promisify} = require('node:util');
const net = require('node:net');
const {chromium} = require('playwright');
const captureFrame = require('./capture-frame.cjs');
const source = resolve(process.env.GFRAME_SOURCE || join(__dirname, '../..'));
const output = resolve(process.argv[2] || join(source, 'docs/assets/4.3.1'));
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
        const publicView = join(app, 'app/views/editorial/editorialPublic.php');
        writeFileSync(publicView, readFileSync(publicView, 'utf8').replace('class="container py-4"', 'class="container py-4 docs-example-article"'));
        mkdirSync(join(app, 'public/css'), {recursive:true});
        cpSync(join(__dirname, 'examples/article.css'), join(app, 'public/css/docs-article.css'));
        const publicMeta = join(app, 'app/views/editorial/editorialPublic.meta.php');
        writeFileSync(publicMeta, readFileSync(publicMeta, 'utf8').replace('return require ', '$meta = require ') + '\n$meta["css"][] = "public/css/docs-article.css"; return $meta;\n');
        const editPath = join(app, 'app/views/editorial/editorialEdit.php');
        writeFileSync(editPath, readFileSync(editPath, 'utf8').replace('<form id="editorialForm"', '<p class="text-body-secondary">Estado editorial: <strong><?= $escape($article["status"]) ?></strong> · Publicación prevista (UTC): <?= $escape($article["publish_at"] ?? "Sin programación") ?></p><form id="editorialForm"'));
        const controllerPath = join(app, 'app/controllers/editorial/EditorialController.php');
        writeFileSync(controllerPath, readFileSync(controllerPath, 'utf8').replace('    public function state(): array', '    public function comparison(): array { $row = $this->record(); if (!$row) return $this->missing(); $model = new EditorialArticle(); $entries = $model->revisions($row["id"], null, $this->scope())["data"]; return ["comparison" => $model->compareRevisions($row["id"], (int)$entries[1]["id"], (int)$entries[0]["id"], null, $this->scope())["data"]]; }\n    public function state(): array'));
        writeFileSync(join(app, 'config/routes/routes_docs_comparison.php'), '<?php RouteBuilder::get("docs-comparison", "editorial/EditorialController@comparison")->middleware(["auth", "admin"])->template("home")->view("docsComparison")->registerFinal();');
        writeFileSync(join(app, 'app/views/editorial/docsComparison.php'), '<?php $revisionComparison=$data["comparison"]; ?><main class="container py-4"><h1>Revisión editorial: qué ha cambiado</h1><p>Estudio Nexo · Compara el texto guardado antes de confirmar una restauración. Los valores se muestran como datos, sin ejecutar HTML.</p><?php include GFrame\\Modules\\ModuleRuntime::file("views", "content-revisions/comparison.php", "content-revisions"); ?></main>');
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
        await captureFrame(page, '.auth-page .col-sm-10', join(output, 'autenticacion.png'));
        const tokens = await page.locator('#login').evaluate(f => Object.fromEntries(new FormData(f)));
        const login = await page.evaluate(async ({base, tokens}) => (await fetch(base + 'ajax/login', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: new URLSearchParams({...tokens, login_email: 'integration@example.com', login_password: 'Fixture-Only-123!'})})).json(), {base, tokens});
        if (login.status !== 'success') throw new Error('Login temporal fallido.');
        await page.setViewportSize({width: 980, height: 1000});
        for (const [path, name] of [['account', 'mi-cuenta.png'], ['admin/users', 'usuarios.png']]) {
            const response = await page.goto(base + path);
            if (response.status() !== 200) throw new Error('No se pudo abrir ' + path);
            await captureFrame(page, '.section.dashboard', join(output, name));
        }
        await page.goto(base + 'admin/media');
        await page.locator('h1').first().waitFor();
        await page.setViewportSize({width: 980, height: 900});
        await page.waitForFunction(() => !document.body.classList.contains('drawer-open'));
        await captureFrame(page, '.section.dashboard', join(output, 'multimedia.png'));
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
        writeFileSync(join(temporary, 'article.html'), article);
        execFileSync(php, ['-r', 'require ' + JSON.stringify(join(app, 'core/Load.php').replaceAll('\\', '/')) + '; DatabaseManager::connection()->prepare("UPDATE articles SET content=? WHERE id=1")->execute([file_get_contents($argv[1])]);', join(temporary, 'article.html')], {windowsHide:true, input:''});
        const post = async (operation, extra = {}) => {
            const fields = await page.locator('#tokens').evaluate(f => Object.fromEntries(new FormData(f)));
            const revision = await page.locator('[name=content_revision]').inputValue();
            const result = await page.evaluate(async ({url, values}) => (await fetch(url, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new URLSearchParams(values)})).json(), {url:base+'ajax/editorial',values:{...fields,operation,content_revision:revision,...extra}});
            if (result.status !== 'success') throw new Error(JSON.stringify(result));
            return result;
        };
        const ready = async () => { await page.goto(base + 'editorial'); await page.waitForFunction(() => window.tinymce?.get('article-content')?.initialized); };
        await ready();
        const revisedArticle = article.replace('tres personas, dos publicaciones semanales', 'cuatro personas, tres publicaciones semanales').replace('La siguiente entrega:', 'Próxima publicación de la revista:');
        await page.evaluate(html => tinymce.get('article-content').setContent(html), revisedArticle);
        await page.locator('[data-autosave-status]').filter({hasText:'Autoguardado privado actualizado'}).waitFor({timeout:20000});
        await ready();
        await page.getByRole('button', {name:'Recuperar autoguardado',exact:true}).waitFor();
        await captureFrame(page, 'main', join(output,'autoguardado.png'));
        await page.getByRole('button', {name:'Recuperar autoguardado',exact:true}).click();
        await page.getByRole('button', {name:'Guardar',exact:true}).click();
        await page.waitForFunction(() => JSON.parse(document.getElementById('editorialForm').dataset.result || '{}').status === 'success');
        await captureFrame(page, 'main', join(output,'editor.png'));
        await page.goto(base+'preview');
        await page.locator('#articleBody').filter({hasText:'Una redacción más clara'}).waitFor();
        await captureFrame(page, 'main', join(output,'vista-previa.png'));
        await page.goto(base+'docs-comparison');
        await page.locator('table').waitFor();
        await captureFrame(page, 'main', join(output,'revisiones.png'));
        await ready();
        const publicationDate = new Date(Date.now()+7*86400000).toISOString().slice(0,19).replace('T',' ');
        await post('schedule',{publish_at:publicationDate});
        await ready();
        await page.evaluate(() => Promise.all([...tinymce.get('article-content').getDoc().images].map(image => image.decode())));
        await captureFrame(page, 'main', join(output,'publicacion-programada.png'));
        const captures = ['multimedia.png','editor.png','vista-previa.png','autenticacion.png','mi-cuenta.png','usuarios.png','autoguardado.png','revisiones.png','publicacion-programada.png'];
        await page.setContent('<!doctype html><html lang="es"><meta charset="utf-8"><style>body{font:14px sans-serif;display:flex;flex-wrap:wrap;gap:12px;background:#eee}figure{margin:0;padding:8px;background:white;width:430px}img{width:100%;height:340px;object-fit:contain;object-position:top}figcaption{padding:6px}</style>' + captures.map(file => '<figure><figcaption>' + file + '</figcaption><img src="data:image/png;base64,' + readFileSync(join(output,file)).toString('base64') + '"></figure>').join(''));
        await page.screenshot({path:join(source,'.phpunit.cache/more-help-sheet.png'),fullPage:true});
        await ctx.close();
        process.stdout.write('Nueve capturas con encuadre completo, incluida multimedia y la plantilla pública de lectura.\n');
    } finally {
        if (browser) await browser.close();
        for (const server of servers) if (server.exitCode === null) { const ended = new Promise(done => server.once('exit', done)); server.kill(); await ended; }
        const path = realpathSync(temporary);
        if (!path.startsWith(realpathSync(tmpdir()) + sep) || !/^gframe-help-capture-/.test(path.split(sep).pop())) throw new Error('Ruta temporal no válida.');
        rmSync(path, {recursive: true, force: true});
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
