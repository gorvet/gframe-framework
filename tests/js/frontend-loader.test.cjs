const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

function load(failedFile) {
  const scripts = [], events = [], window = {};
  const mount = { appendChild(script) {
    scripts.push(script.src);
    if (script.src.endsWith(failedFile || 'never')) script.onerror();
    else script.onload();
  } };
  const document = {
    currentScript: { src: '/public/js/core/utils.js?v=1', parentNode: mount },
    querySelector: () => null,
    createElement: () => ({ setAttribute() {} }),
    dispatchEvent: event => events.push(event)
  };
  const context = { window, document, CustomEvent: function(type, options) {
    this.type = type; this.detail = options && options.detail;
  } };
  const source = readFileSync(join(__dirname, '../../resources/modules/frontend-core/public/utils.js'), 'utf8');
  runInNewContext(source, context);
  runInNewContext(source, context);
  return { window, scripts, events };
}

test('el cargador confirma la carga completa una sola vez', () => {
  const result = load();
  assert.equal(result.scripts.length, 4);
  assert.equal(result.window.__gfUtilsReady, true);
  assert.deepEqual(result.events.map(event => event.type), ['gfutilsready']);
});

test('un fallo permite continuar pero no anuncia disponibilidad completa', () => {
  const result = load('forms.js');
  assert.equal(result.scripts.length, 4);
  assert.equal(result.window.__gfUtilsReady, false);
  assert.deepEqual(result.events.map(event => event.type), ['gfutilserror']);
  assert.equal(result.events[0].detail.files[0], '/public/js/core/utils/forms.js');
});
