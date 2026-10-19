const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawn, execFile, execFileSync} = require('node:child_process');
const {promisify} = require('node:util');
const {mkdtempSync, rmSync, realpathSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join, sep} = require('node:path');
const net = require('node:net');

test('Installed application: authenticated editorial flow over real HTTP and cron CLI', {skip:!process.env.GFRAME_TEST_BROWSER,timeout:120000}, async()=>{
  const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
  const root = mkdtempSync(join(tmpdir(),'gframe-editorial-installed-'));
  const fixture = join(__dirname,'../fixtures/editorial-integration/setup.php');
  const port = await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  const base = 'http://127.0.0.1:'+port+'/';
  let server,browser,serverLog='';const errors=[];
  try {
    const installed = JSON.parse(execFileSync(php,[fixture,root,base],{windowsHide:true,encoding:'utf8'}));assert.equal(installed.status,'success');
    server = spawn(php,['-S','127.0.0.1:'+port,'-t',root,join(root,'router.php')],{windowsHide:true});
    server.stderr.on('data',d=>serverLog+=d);server.stdout.on('data',d=>serverLog+=d);
    const {chromium}=require('playwright');browser=await chromium.launch({headless:true,executablePath:process.env.GFRAME_TEST_BROWSER});
    const context=await browser.newContext({viewport:{width:1440,height:1000},timezoneId:'UTC'});
    // Real PHP HTTP responses/cookies; only the browser's local socket transport is bridged.
    const bridge=async ctx=>ctx.route(base+'**',async route=>{
      const req=route.request();const input=Buffer.from(JSON.stringify({url:req.url(),method:req.method(),body:req.postData()||'',headers:req.headers()})).toString('base64');
      try {const {stdout}=await promisify(execFile)(php,[join(__dirname,'../fixtures/editorial-integration/http-bridge.php'),input],{windowsHide:true,maxBuffer:8*1024*1024});const response=JSON.parse(stdout);await route.fulfill({...response,body:Buffer.from(response.body,'base64')});}
      catch(error){serverLog+='\nBridge: '+error.message;await route.abort().catch(()=>{});}
    });
    await bridge(context);
    const page=await context.newPage();
    page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());page.setDefaultTimeout(12000);
    await page.goto(base+'login');
    const loginTokens=await page.locator('#login').evaluate(f=>Object.fromEntries(new FormData(f)));
    const request=async values=>page.evaluate(async({url,values})=>(await fetch(url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams(values)})).json(),values);
    const login=await request({url:base+'ajax/login',values:{...loginTokens,login_email:'integration@example.com',login_password:'Fixture-Only-123!'}});
    assert.equal(login.status,'success',JSON.stringify(login));
    const ready=async()=>{await page.goto(base+'editorial');await page.waitForFunction(()=>window.tinymce?.get('article-content')?.initialized);};
    await ready();
    const tokens=()=>page.locator('#tokens').evaluate(f=>Object.fromEntries(new FormData(f)));
    const revision=()=>page.locator('[name=content_revision]').inputValue();
    const post=async(operation,extra={})=>request({url:base+'ajax/editorial',values:{...await tokens(),operation,content_revision:await revision(),...extra}});
    assert.equal((await post('save',{id:2,content:'No autorizado'})).code,'not_found');
    const noCsrf=await request({url:base+'ajax/editorial',values:{operation:'save',content:'No autorizado'}});assert.notEqual(noCsrf.status,'success');
    const publicPage=await context.newPage();await publicPage.goto(base+'article');assert.match(await publicPage.locator('#articleBody').innerText(),/Artículo inicial/);
    // The button opens the public template; confirmed private autosave refreshes that live tab.
    await page.getByRole('button',{name:'Más herramientas',exact:true}).click();
    const opened=context.waitForEvent('page');await page.getByRole('button',{name:'Vista previa del sitio',exact:true}).click();const preview=await opened;await preview.waitForLoadState();
    const response=await preview.reload();assert.match(response.headers()['cache-control'],/private.*no-store/);assert.match(response.headers()['x-robots-tag'],/noindex/);
    await page.bringToFront();await page.evaluate(()=>tinymce.get('article-content').setContent('<p>Cambio privado</p>'));
    await page.locator('[data-autosave-status]').filter({hasText:'Autoguardado privado actualizado'}).waitFor({timeout:20000});
    await preview.bringToFront();await preview.locator('#articleBody').filter({hasText:'Cambio privado'}).waitFor({timeout:20000});
    await publicPage.reload();assert.match(await publicPage.locator('#articleBody').innerText(),/Artículo inicial/);
    await page.bringToFront();const old=await revision();await page.getByRole('button',{name:'Guardar',exact:true}).click();
    await page.waitForFunction(()=>document.getElementById('editorialForm').dataset.result);
    assert.equal(JSON.parse(await page.locator('#editorialForm').getAttribute('data-result')).status,'success');
    assert.notEqual(await revision(),old);assert.equal((await post('save',{content_revision:old,content:'Obsoleto'})).code,'content_conflict');
    await publicPage.reload();assert.match(await publicPage.locator('#articleBody').innerText(),/Cambio privado/);
    await ready();const entries=await page.locator('#editorialHistory li').count();assert.equal(entries,2);
    const firstEntry=await page.locator('#editorialHistory li').last().getAttribute('data-entry-id');assert.equal((await post('restore_revision',{entry_id:firstEntry})).status,'success');
    await ready();assert.match(await page.evaluate(()=>tinymce.get('article-content').getBody().textContent),/Artículo inicial/);
    assert.equal((await post('clear_revisions',{confirmed:'1'})).status,'success');await ready();assert.equal(await page.locator('#editorialHistory li').count(),0);
    assert.equal((await post('trash')).status,'success');await ready();assert.equal((await publicPage.goto(base+'article')).status(),404);
    assert.equal((await post('restore')).status,'success');await ready();assert.equal((await publicPage.goto(base+'article')).status(),200);
    const scheduleAt = async seconds=>{
      await page.locator('#publish_at').fill(new Date(Date.now()+seconds*1000).toLocaleString('sv-SE',{timeZone:'UTC'}).replace(' ','T'));
      await page.locator('#editorialForm').evaluate(f=>delete f.dataset.result);await page.getByRole('button',{name:'Programar',exact:true}).click();
      await page.waitForFunction(()=>document.getElementById('editorialForm').dataset.result);assert.equal(JSON.parse(await page.locator('#editorialForm').getAttribute('data-result')).status,'success');
    };
    await scheduleAt(60);assert.equal((await publicPage.goto(base+'article')).status(),404);
    assert.equal((await post('cancel')).status,'success');await ready();
    await scheduleAt(4);await page.waitForTimeout(5000);
    execFileSync(php,[join(root,'bin/gframe-cron.php')],{cwd:root,windowsHide:true,encoding:'utf8'});
    assert.equal((await publicPage.goto(base+'article')).status(),200);assert.match(await publicPage.locator('#articleBody').innerText(),/Artículo inicial/);
    await ready();const finalRevision=await revision();
    execFileSync(php,[join(root,'bin/gframe-cron.php')],{cwd:root,windowsHide:true,encoding:'utf8'});await ready();assert.equal(await revision(),finalRevision);
    assert.equal((await post('delete',{confirmed:'1'})).code,'content_conflict');
    assert.equal((await post('trash')).status,'success');await ready();assert.equal((await post('delete',{confirmed:'0'})).code,'confirmation_required');
    assert.equal((await post('delete',{confirmed:'1'})).status,'success');assert.equal((await publicPage.goto(base+'article')).status(),404);
    const anonymousRequest=Buffer.from(JSON.stringify({url:base+'preview-state',method:'GET',headers:{},body:''})).toString('base64');
    const anonymousResponse=JSON.parse((await promisify(execFile)(php,[join(__dirname,'../fixtures/editorial-integration/http-bridge.php'),anonymousRequest],{windowsHide:true})).stdout);
    assert.ok([301,302,303,307,401,403].includes(anonymousResponse.status));assert.doesNotMatch(Buffer.from(anonymousResponse.body,'base64').toString(),/Cambio privado/);
    assert.deepEqual(errors,[]);
  } catch(error) {error.message+='\nPHP: '+serverLog.slice(-5000);throw error;}
  finally {
    if(browser)await browser.close();if(server && server.exitCode===null){const exited=new Promise(resolve=>server.once('exit',resolve));server.kill();await exited;}
    const cleanupRoot=realpathSync(root);assert.ok(cleanupRoot.startsWith(realpathSync(tmpdir())+sep+'gframe-editorial-installed-'));
    rmSync(cleanupRoot,{recursive:true,force:true,maxRetries:3,retryDelay:100});
  }
});
