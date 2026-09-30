const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');
const source = readFileSync(join(__dirname, '../../resources/skeleton/public/js/app/home/mngnoadmin.js'), 'utf8');

function classes(initial = []) {
  const values = new Set(initial);
  return { contains: x => values.has(x), add: x => values.add(x), remove: x => values.delete(x),
    toggle(x, force) { const on = force === undefined ? !values.has(x) : force; on ? values.add(x) : values.delete(x); return on; } };
}
function setup({ menu = false, anchors = false, local = false } = {}) {
  const listeners = {}, loads = [], scrolls = [], replaced = [];
  const body = { style: { overflow: 'auto' }, classList: classes(), appendChild() {} };
  const button = { classList: classes(['gicon-menu']), attrs: {},
    setAttribute(k, v) { this.attrs[k] = v; }, addEventListener(k, fn) { this[k] = fn; }, focus() { this.focused = true; } };
  const target = { id: 'servicios', getBoundingClientRect: () => ({ top: 300 }), closest: () => null };
  const link = { classList: classes(), getAttribute: k => k === 'href' ? (local ? '#servicios' : '/?filter=other#servicios') : null };
  const nav = { contains: x => x === link, querySelectorAll: () => anchors ? [link] : [] };
  const header = { offsetHeight: 0, classList: classes(), contains: x => x === link,
    getBoundingClientRect: () => ({ width: 1000 }), cloneNode: () => ({ style: {}, offsetHeight: 0 }) };
  const location = { href: 'https://example.test/?filter=current', origin: 'https://example.test', pathname: '/', search: '?filter=current', hash: '' };
  const document = { body, documentElement: { clientWidth: 1000, scrollHeight: 2000 },
    querySelector: selector => ({ '#header': anchors ? header : null, '#mng': anchors ? nav : null, '.mobile-nav-toggle': menu ? button : null })[selector] || null,
    getElementById: id => id === target.id ? target : null,
    createElement: () => ({ style: {}, setAttribute() {}, appendChild() {}, remove() {} }),
    addEventListener(k, fn) { (listeners[k] ||= []).push(fn); } };
  const window = { innerWidth: 1000, innerHeight: 800, pageYOffset: 0, scrollY: 0, location,
    addEventListener(k, fn) { if (k === 'load') loads.push(fn); }, matchMedia: () => ({ matches: true }), scrollTo: (...args) => scrolls.push(args) };
  function Element() {}
  link.closest = () => link;
  Object.setPrototypeOf(link, Element.prototype);
  runInNewContext(source, { document, window, location, URL, Element,
    history: { replaceState: (...args) => replaced.push(args) }, setTimeout: fn => fn(),
    requestAnimationFrame() { throw new Error('Reduced motion must not animate'); }, cancelAnimationFrame() {} });
  return { listeners, loads, scrolls, replaced, body, button, link, target };
}

test('carga sin header, menú ni botón opcional', () => {
  const env = setup();
  env.loads.forEach(fn => fn());
  env.listeners.scroll.forEach(fn => fn());
});
test('menú móvil y Escape restauran overflow y foco', () => {
  const env = setup({ menu: true });
  env.button.click();
  assert.equal(env.body.style.overflow, 'hidden');
  assert.equal(env.button.attrs['aria-expanded'], 'true');
  env.listeners.keydown[0]({ key: 'Escape' });
  assert.equal(env.body.style.overflow, 'auto');
  assert.equal(env.button.attrs['aria-expanded'], 'false');
  assert.equal(env.button.focused, true);
});
test('otra consulta no se intercepta; ancla local respeta movimiento reducido', () => {
  const env = setup({ anchors: true });
  let prevented = false;
  const event = { target: env.link, preventDefault() { prevented = true; } };
  env.listeners.click[0](event);
  assert.equal(prevented, false);
  env.link.getAttribute = k => k === 'href' ? '#servicios' : null;
  env.listeners.click[0](event);
  assert.equal(prevented, true);
  assert.deepEqual(env.scrolls, [[0, 300]]);
  assert.equal(env.replaced[0][2], '/?filter=current');
});
test('anclas locales funcionan sin IntersectionObserver y meta carga el recurso', () => {
  const env = setup({ anchors: true, local: true });
  env.loads.forEach(fn => fn());
  env.listeners.click[0]({ target: env.link, preventDefault() {} });
  assert.deepEqual(env.scrolls, [[0, 300]]);
  assert.match(readFileSync(join(__dirname, '../../resources/skeleton/app/views/home/home.group.meta.php'), 'utf8'), /public\/js\/app\/home\/mngnoadmin\.js/);
});
