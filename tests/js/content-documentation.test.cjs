const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {runInNewContext} = require('node:vm');
const root = join(__dirname, '../..');

test('Los ejemplos Markdown de la guía usan la API distribuida', () => {
  const context = {};
  runInNewContext(readFileSync(join(root, 'resources/modules/markdown/public/markdown.js'), 'utf8'), context);
  const doc = readFileSync(join(root, 'docs/markdown.md'), 'utf8');
  const example = [...doc.matchAll(/```js\r?\n([\s\S]*?)\r?\n```/g)][0][1];
  runInNewContext(example + '\nresult = {formatted, plainMarkdown};', context);
  assert.equal(context.result.formatted, '<strong>Hola</strong><br><em>Revisa tu cuenta</em>');
  assert.equal(context.result.plainMarkdown, '*Hola*\n_Revisa tu cuenta_');
});

test('El filtro léxico documentado oculta solo elementos sin coincidencia', () => {
  let listener;
  const input = {value: 'campañs', addEventListener: (_, callback) => {listener = callback;}};
  const items = [{textContent: 'Campañas programadas'}, {textContent: 'Biblioteca multimedia'}];
  const context = {window: {}, document: {
    querySelector: () => input,
    querySelectorAll: () => items
  }};
  runInNewContext(readFileSync(join(root, 'resources/modules/lexical-search/public/lexical-search.js'), 'utf8'), context);
  const doc = readFileSync(join(root, 'docs/lexical-search.md'), 'utf8');
  const example = [...doc.matchAll(/```js\r?\n([\s\S]*?)\r?\n```/g)][1][1];
  runInNewContext(example, context);
  listener();
  assert.equal(items[0].hidden, false);
  assert.equal(items[1].hidden, true);
  input.value = '';
  listener();
  assert.equal(items[1].hidden, false);
});
