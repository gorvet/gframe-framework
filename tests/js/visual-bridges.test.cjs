const assert = require('node:assert/strict');
const {test} = require('node:test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {createContext, runInContext} = require('node:vm');
const root = join(__dirname, '../../resources/modules');
const bridges = ['coloris/public/gframe-coloris.css','intl-tel-input/public/css/gframe-intl-tel-input.css',
 'flatpickr/public/gframe-flatpickr.css','swiper/public/gframe-swiper.css','owl-carousel/public/assets/gframe-owl-carousel.css',
 'venobox/public/gframe-venobox.css','jquery-ui/public/gframe-jquery-ui.css','tinymce/public/gframe-tinymce.css'];
for (const file of bridges) test(file + ' utiliza las variables y no introduce otro tema', () => {
  const css = readFileSync(join(root, file), 'utf8');
  assert.ok(css.includes('var(--bs-'));
  assert.ok(css.includes('--bs-body-font-family'));
  assert.doesNotMatch(css, /data-gf-theme|#[a-f0-9]{3,8}\b/i);
});
test('ChartJS adapta texto, ejes y tooltip sin cambiar datasets; el observador se desconecta', () => {
  let callback, disconnected = false;
  const context = createContext({window:{getComputedStyle:() => ({getPropertyValue:key => key === '--bs-body-font-size' ? '14px' : key})},
    document:{documentElement:{}}, MutationObserver:class {constructor(fn) {callback=fn;} observe() {} disconnect() {disconnected=true;}}});
  runInContext(readFileSync(join(root,'chartjs/public/gframe-chartjs.js'),'utf8'),context);
  let updates = 0;
  const chart = {canvas:{},options:{scales:{x:{},y:{}},plugins:{}},data:{datasets:[{backgroundColor:'original'}]},update() {updates++;}};
  const stop = context.window.GFrameChartTheme.watch(chart);
  callback(); stop();
  assert.equal(chart.data.datasets[0].backgroundColor,'original');
  assert.equal(chart.options.font.size,14);
  assert.equal(chart.options.scales.x.ticks.color,'--bs-body-color');
  assert.equal(updates,2); assert.equal(disconnected,true);
});
test('El editor aplica variables al iframe y desconecta la observación al retirarlo', () => {
  const js = readFileSync(join(root,'rich-text-editor/public/rich-text-editor.js'),'utf8');
  assert.match(js,/body\.style\.setProperty\(name/);
  assert.match(js,/editor\.on\('remove'.*observer\.disconnect/);
  assert.match(js,/background: var\(--bs-tertiary-bg\)/);
});
