const assert = require('node:assert/strict');
const {test} = require('node:test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {createContext, runInContext} = require('node:vm');

function prototype() {
  const context = createContext({window: {}, document: {}});
  runInContext(readFileSync(join(__dirname, '../../resources/modules/gfselect/public/gf-select.js'), 'utf8'), context);
  return context.window.GFSelect.prototype;
}

test('GF Select distingue valores simples, múltiples y límites del atributo', () => {
  const api = prototype();
  const select = {value: 'es', dataset: {maxSelections: '3'},
    selectedOptions: [{value: 'education'}, {value: 'health'}]};
  assert.equal(api.getValue.call({select, settings: {multiple: false}}), 'es');
  assert.deepEqual(Array.from(api.getValue.call({select, settings: {multiple: true}})),
    ['education', 'health']);
  assert.equal(api.resolveMaximum.call({select}, null), 3);
  assert.equal(api.resolveMaximum.call({select}, 0), 0);
  assert.equal(api.resolveMaximum.call({select}, 2), 2);
});

test('El cambio externo sincroniza sin invocar el callback de elección', () => {
  let refreshed = 0, notified = 0;
  const instance = {invalid: true, refresh() {refreshed++;},
    settings: {onChange() {notified++;}}};
  prototype().handleNativeChange.call(instance);
  assert.equal(instance.invalid, false);
  assert.equal(refreshed, 1);
  assert.equal(notified, 0);
});

test('GFSelect sincroniza la validación explícita, nativa y aria-invalid', () => {
  const attrs = {}, classes = {};
  const instance = {
    wrapper: {classList: {toggle: (key, value) => {classes[key] = value;}}},
    toggle: {setAttribute: (key, value) => {attrs[key] = value;}},
    select: {form: null, validity: {valid: true}, classList: {contains: () => false}},
    invalid: true
  };
  prototype().syncValidity.call(instance);
  assert.equal(classes['is-invalid'], true);
  assert.equal(attrs['aria-invalid'], 'true');
  instance.invalid = false;
  prototype().syncValidity.call(instance);
  assert.equal(attrs['aria-invalid'], 'false');
  instance.select.form = {classList: {contains: () => true}};
  instance.select.validity.valid = false;
  prototype().syncValidity.call(instance);
  assert.equal(attrs['aria-invalid'], 'true');
});

test('GFSelect cierra el desplegable cuando el select pasa a disabled', () => {
  let closed = 0;
  const instance = {
    wrapper: {classList: {toggle() {}}}, destroyed: false,
    select: {multiple: false, disabled: true}, settings: {},
    configuredCloseOnSelect: null, menu: {removeAttribute() {}}, toggle: {},
    resolveMaximum: () => 0, closeMenu: () => {closed++;},
    renderSelection() {}, renderOptions() {}, syncValidity() {}, isOpen: () => false
  };
  prototype().refresh.call(instance);
  assert.equal(closed, 1);
  assert.equal(instance.toggle.disabled, true);
});

test('GFSelect mantiene un puente de variables y estados sin borde más grueso', () => {
  const css = readFileSync(join(__dirname, '../../resources/modules/gfselect/public/gf-select.css'), 'utf8');
  for (const name of ['--bs-body-font-size', '--bs-form-control-bg', '--bs-border-color', '--bs-primary-bg-subtle', '--bs-danger', '--bs-form-control-disabled-bg']) {
    assert.ok(css.includes(name), name);
  }
  assert.match(css, /:not\(:disabled\):hover/);
  assert.match(css, /border-right: \.12rem solid currentColor/);
  assert.match(css, /rotate\(225deg\)/);
  assert.doesNotMatch(css, /\.tpl-app|\.gf-select--app/);
  assert.doesNotMatch(css, /\[data-gf-theme/);
});

test('La personalización nativa de Dane reside en common, sin depender de su plantilla', () => {
  const css = readFileSync(join(__dirname, '../../resources/skeleton/public/css/common.css'), 'utf8');
  assert.match(css, /\.form-select \{\s*min-height: 2\.75rem;/);
  assert.match(css, /\.form-select\[multiple\] \{/);
  assert.match(css, /\.form-select:not\(:disabled\):hover/);
});
