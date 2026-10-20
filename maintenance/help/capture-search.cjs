const path=require('node:path'),{execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const source=path.resolve(process.env.GFRAME_SOURCE||path.join(__dirname,'../..'));
(async()=>{
 const html=execFileSync(process.env.GFRAME_TEST_PHP||'C:/xampp/php/php.exe',[path.join(__dirname,'examples/search.php'),source],{windowsHide:true}).toString();
 const browser=await chromium.launch({headless:true,executablePath:process.env.GFRAME_TEST_BROWSER});
 try{
  const page=await browser.newPage({viewport:{width:980,height:900}});
  await page.setContent(html);
  await page.locator('body').screenshot({path:path.resolve(process.argv[2]||path.join(source,'docs/assets/4.3.1/busqueda.png'))});
  console.log('Búsqueda: dos resultados calculados por el motor original; interfaz identificada como ejemplo.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
