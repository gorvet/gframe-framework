const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

function setup(saved, blocked = false) {
  const values = new Map(saved ? [['gf-theme', saved]] : []);
  const root = { dataset: {}, style: {} }, listeners = {}, system = { matches: true, addEventListener(type, fn) { this.change = fn; } };
  const window = { matchMedia: query => query.includes('prefers-color') ? system : { matches: false, addEventListener() {} }, addEventListener: (type, fn) => { listeners[type] = fn; } };
  const document = { documentElement: root, body: { classList: { toggle() {} } }, getElementById: id => id === 'sidebar' ? {} : null, querySelector: () => null, querySelectorAll: () => [], addEventListener() {} };
  const localStorage = {
    getItem(key) { if (blocked) throw new Error('blocked'); return values.get(key) || null; },
    setItem(key, value) { if (blocked) throw new Error('blocked'); values.set(key, value); },
    removeItem(key) { values.delete(key); }
  };
  const context = { window, document, localStorage };
  for (const file of ['preload.js', 'admin.js']) runInNewContext(readFileSync(join(__dirname, '../../resources/modules/admin-panel/javascript', file), 'utf8'), context);
  return { window, root, values, system, listeners };
}

test('el tema restaura, guarda y sincroniza la elección con Bootstrap', () => {
  const state = setup('light');
  assert.equal(state.window.GFTheme.get(), 'light');
  state.window.GFTheme.set('dark');
  assert.equal(state.values.get('gf-theme'), 'dark');
  assert.equal(state.root.dataset.bsTheme, 'dark');
  assert.equal(state.root.dataset.gfTheme, undefined);
  state.listeners.storage({ key: 'gf-theme', newValue: 'light' });
  state.system.change({ matches: true });
  assert.equal(state.window.GFTheme.get(), 'light');
  state.window.GFTheme.resetToSystem();
  assert.equal(state.values.has('gf-theme'), false);
  assert.equal(state.window.GFTheme.get(), 'dark');
  state.system.change({ matches: false });
  assert.equal(state.window.GFTheme.get(), 'light');
});

test('el tema funciona con almacenamiento bloqueado y cambios no persistentes', () => {
  const state = setup(null, true);
  assert.equal(state.window.GFTheme.get(), 'dark');
  state.window.GFTheme.set('light', false);
  assert.equal(state.window.GFTheme.get(), 'light');
  state.window.GFTheme.set('invalid');
  assert.equal(state.window.GFTheme.get(), 'light');
  state.window.GFTheme.set('dark');
  assert.equal(state.root.style.colorScheme, 'dark');
});
