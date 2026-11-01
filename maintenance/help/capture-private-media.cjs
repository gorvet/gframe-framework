// Real installed application, synthetic files and authenticated native media screens.
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net');
const {spawn,execFileSync,execFile}=require('node:child_process'),{promisify}=require('node:util');
const {chromium}=require('playwright');
const source=path.resolve(__dirname,'../..'),php=process.env.GFRAME_TEST_PHP||'php';
const root=fs.mkdtempSync(path.join(os.tmpdir(),'gframe-private-capture-'));
const output=path.join(source,'docs/assets/4.7.1');let server,browser;
(async()=>{try{
 fs.mkdirSync(output,{recursive:true});
 const port=await new Promise(done=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>done(p));});});
 const base='http://127.0.0.1:'+port+'/';
 execFileSync(php,[path.join(source,'tests/fixtures/editorial-integration/setup.php'),root,base],{windowsHide:true});
 execFileSync(php,[path.join(__dirname,'seed-private-media.php'),source,root],{windowsHide:true});
 server=spawn(php,['-S','127.0.0.1:'+port,'-t',root,path.join(root,'router.php')],{windowsHide:true,stdio:'ignore'});
 browser=await chromium.launch({headless:true,executablePath:process.env.GFRAME_TEST_BROWSER});
 const context=await browser.newContext({viewport:{width:1360,height:1000},timezoneId:'UTC'});
 await context.route(base+'**',async route=>{const r=route.request(),input=Buffer.from(JSON.stringify({url:r.url(),method:r.method(),body:r.postData()||'',headers:r.headers()})).toString('base64');const {stdout}=await promisify(execFile)(php,[path.join(source,'tests/fixtures/editorial-integration/http-bridge.php'),input],{windowsHide:true,maxBuffer:12*1024*1024});const response=JSON.parse(stdout);await route.fulfill({...response,body:Buffer.from(response.body,'base64')});});
 const page=await context.newPage();await page.goto(base+'login');
 const tokens=await page.locator('#login').evaluate(f=>Object.fromEntries(new FormData(f)));
 const login=await page.evaluate(async({base,tokens})=>(await fetch(base+'ajax/login',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams({...tokens,login_email:'integration@example.com',login_password:'Fixture-Only-123!'})})).json(),{base,tokens});
 if(login.status!=='success')throw Error('Falló la autenticación temporal.');
 await page.goto(base+'admin/media?source=capture&view=list');
 await page.locator('#media-visibility').waitFor();
 await page.locator('.m-list-card').first().waitFor();
 await page.locator('.section.dashboard').screenshot({path:path.join(output,'medios-publicos-privados.png'),animations:'disabled'});
 await page.locator('#media-visibility').selectOption('private');
 await page.waitForFunction(()=>document.querySelectorAll('#knowledgeMediaList .m-list-card').length===2);
 await page.locator('.section.dashboard').screenshot({path:path.join(output,'medios-filtro-privados.png'),animations:'disabled'});
 await page.locator('.m-list-card').first().click();
 await page.locator('#editMediaModal').waitFor({state:'visible'});
 await page.waitForFunction(()=>document.querySelector('#media-visibility-label').textContent==='Privado');
 await page.locator('#editMediaModal .modal-content').screenshot({path:path.join(output,'medio-privado-detalles.png'),animations:'disabled'});
 console.log('Tres capturas reales: biblioteca mixta, filtro privado y detalles privados.');
}finally{
 if(browser)await browser.close();if(server&&server.exitCode===null){const end=new Promise(done=>server.once('exit',done));server.kill();await end;}
 const resolved=fs.realpathSync(root);if(!resolved.startsWith(fs.realpathSync(os.tmpdir())+path.sep+'gframe-private-capture-'))throw Error('Directorio temporal inválido.');fs.rmSync(resolved,{recursive:true,force:true});
}})().catch(e=>{console.error(e);process.exitCode=1;});
