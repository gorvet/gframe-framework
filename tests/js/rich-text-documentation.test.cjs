const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {runInNewContext} = require('node:vm');

test('La personalización documentada registra un botón visible y conserva el ciclo de vida', () => {
  const root = join(__dirname, '../..');
  let options;
  let saved = 0;
  let removed = 0;
  const element = {id: 'articleContent', dataset: {editorMinHeight: '580'}};
  const instances = {};
  const tinymce = {
    get: id => instances[id],
    init: config => {options = config; instances[element.id] = {remove: () => {removed++; delete instances[element.id];}, getBody: () => null};},
    triggerSave: () => {saved++;}
  };
  const jquery = () => {};
  jquery.extend = (_, target, ...sources) => Object.assign(target, ...sources);
  const context = {window: {}, document: {querySelectorAll: () => [element]}, jQuery: jquery, tinymce, site_url: 'https://example.test/'};
  runInNewContext(readFileSync(join(root, 'resources/modules/rich-text-editor/public/rich-text-editor.js'), 'utf8'), context);
  const doc = readFileSync(join(root, 'docs/rich-text-editor.md'), 'utf8');
  const example = [...doc.matchAll(/```javascript\r?\n([\s\S]*?)\r?\n```/g)][0][1];
  runInNewContext(example, context);
  context.window.AdminRichTextEditor.init(element);
  assert.ok(options.toolbar.includes('customAction'));
  let button;
  let inserted;
  options.setup({ui: {registry: {addButton: (name, config) => {button = config; assert.equal(name, 'customAction');}}}, insertContent: html => {inserted = html;}});
  button.onAction();
  assert.equal(inserted, '<p>Nuevo bloque</p>');
  assert.equal(options.language_url, 'https://example.test/public/vendors/external/tinymce/langs/es.js');
  context.window.AdminRichTextEditor.saveAll();
  context.window.AdminRichTextEditor.destroyAll(context.document);
  assert.equal(saved, 1);
  assert.equal(removed, 1);
});
