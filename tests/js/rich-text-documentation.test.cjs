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
  const example = [...doc.matchAll(/```javascript\r?\n([\s\S]*?)\r?\n```/g)].find(match => match[1].includes("addButton('customAction'"))[1];
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

test('El ejemplo de protección confirma únicamente el snapshot enviado tras éxito de negocio', () => {
  const doc = readFileSync(join(__dirname, '../../docs/rich-text-editor.md'), 'utf8');
  const example = [...doc.matchAll(/```javascript\r?\n([\s\S]*?)\r?\n```/g)].find(match => match[1].includes('function saveArticle(form)'))[1];
  const snapshot = Object.freeze({editorID: 'articleContent', content: '<p>Enviado</p>'});
  const acknowledgements = [];
  let done, request, valid = false;
  const fields = new Map();
  const jquery = () => ({serializeArray: () => [{name: 'csrfToken', value: 'fixture-token'}]});
  jquery.ajax = options => { request = options; return {done: callback => { done = callback; }}; };
  const form = {action: '/save-fixture', checkValidity: () => valid, classList: {add: () => {}}};
  const context = {
    window: {AdminRichTextEditor: {saveAll() {}, getSaveSnapshot: () => snapshot, markSaved: value => acknowledgements.push(value)}},
    $: jquery, FormData: class {set(key, value) {fields.set(key, value);}}
  };
  runInNewContext(example, context);
  context.saveArticle(form); assert.equal(request, undefined, 'No envía un formulario inválido.');
  valid = true; context.saveArticle(form);
  assert.equal(request.url, form.action); assert.equal(fields.get('content'), snapshot.content);
  assert.equal(fields.get('csrfToken'), 'fixture-token');
  done({status: 'error'}); assert.equal(acknowledgements.length, 0);
  done({status: 'success'}); assert.equal(acknowledgements[0], snapshot);
});
