const {test}=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {join}=require('node:path');
test('TinyMCE site preview button and live preview reload', {skip:!process.env.GFRAME_TEST_BROWSER,timeout:45000},async()=>{
 const {chromium}=require('playwright');
 const browser=await chromium.launch({headless:true,executablePath:process.env.GFRAME_TEST_BROWSER});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});let version='v1',views=0;const errors=[],missing=[];
 const base=join(__dirname,'../..');
 await context.route('https://preview.test/**',async route=>{
  const path=new URL(route.request().url()).pathname;
  let body,type='text/javascript';
  if(path==='/'){type='text/html';body='<!doctype html><script src="/jquery.js"></script><script src="/tinymce/tinymce.min.js"></script><textarea style="width:100%" id="content" class="js-rich-text-editor" data-editor-preview-url="/preview" data-editor-preview-label="Vista previa del sitio" data-editor-more="Más herramientas" data-editor-protect="0">Original</textarea><script src="/editor.js"></script>';}
  else if(path==='/preview'){views++;type='text/html';body='<div data-content-preview data-state-url="/state" data-version="'+version+'"><p data-preview-status hidden></p></div><script src="/live.js"></script>';}
  else if(path==='/state'){type='application/json';body=JSON.stringify({status:'success',data:{version}});}
  else if(path==='/public/vendors/external/tinymce/langs/es.js'){body=readFileSync(join(base,'resources/modules/tinymce/public/langs/es.js'));}
  else {const file=path==='/jquery.js'?'resources/modules/jquery/public/jquery.min.js':path==='/editor.js'?'resources/modules/rich-text-editor/public/rich-text-editor.js':path==='/live.js'?'resources/modules/content-preview/public/content-preview.js':'resources/modules/tinymce/public/'+path.replace(/^\/tinymce\//,'');try{body=readFileSync(join(base,file));if(path.endsWith('.css'))type='text/css';}catch(_){missing.push(path);await route.fulfill({status:404,body:''});return;}}
  await route.fulfill({contentType:type+'; charset=utf-8',body});
 });
 try{
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
  await page.goto('https://preview.test/');await page.waitForFunction(()=>window.tinymce?.get('content')?.initialized,{},{timeout:8000}).catch(async error=>{throw new Error(error.message+' '+JSON.stringify({errors,missing,html:await page.locator('body').innerText()}));});
  await page.getByRole('button',{name:'Más herramientas',exact:true}).click({timeout:5000}).catch(async error=>{throw new Error(error.message+' '+await page.locator('body').innerText());});
  const popup=context.waitForEvent('page');await page.getByRole('button',{name:'Vista previa del sitio',exact:true}).click();
  const preview=await popup;await preview.waitForLoadState();assert.equal(new URL(preview.url()).pathname,'/preview');
  version='v2';await preview.waitForTimeout(4000);assert.equal(views,2);
  assert.deepEqual(errors,[]);
 }finally{await context.close();await browser.close();}
});
