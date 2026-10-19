const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

test('Preview polls confirmed versions, pauses, stops on errors and rejects external URLs', async () => {
  let timer, reloads = 0, calls = 0, result = {status:'success',data:{version:'v1'}}, listener;
  const status = {hidden:true,textContent:''};
  const root = {isConnected:true,dataset:{stateUrl:'/state',version:'v1',error:'Error'},querySelector:()=>status};
  const document = {hidden:false,readyState:'complete',querySelectorAll:()=>[root],addEventListener:(_,f)=>listener=f,removeEventListener:()=>{}};
  const window = {location:{href:'https://site.test/preview',origin:'https://site.test',reload:()=>reloads++},setTimeout:f=>{timer=f;return 1;},clearTimeout:()=>{},fetch:async()=>{calls++;return {ok:true,json:async()=>result};}};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../resources/modules/content-preview/public/content-preview.js'),'utf8'),{window,document,URL,WeakMap,AbortController,Error});
  await timer(); assert.equal(calls,1); assert.equal(reloads,0);
  document.hidden=true; await timer(); assert.equal(calls,1);
  document.hidden=false; result={status:'success',data:{version:'v2'}};
  await timer(); assert.equal(reloads,1);
  assert.equal(window.ContentPreview.mount({...root,dataset:{stateUrl:'https://other.test/state',version:'v1'}}),null);
  const next={...root,dataset:{...root.dataset}};
  window.ContentPreview.mount(next); result={status:'unauthorized'};
  await timer(); assert.equal(status.hidden,false); const previous=calls;
  await timer(); assert.equal(calls,previous); assert.equal(reloads,1);
  const last={...root,dataset:{...root.dataset}};
  const instance=window.ContentPreview.mount(last);instance.destroy();await timer();assert.equal(calls,previous);
});
