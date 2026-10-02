const assert = require('node:assert/strict');
const {test} = require('node:test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {createContext, runInContext} = require('node:vm');

function setup() {
  const state = {timers: [], toasts: [], calls: []};
  const context = createContext({
    document: {querySelectorAll: () => []}, bootstrap: {},
    Swal: {fire: options => { state.calls.push(options); return Promise.resolve({isConfirmed: true}); }},
    setTimeout: (callback, ms) => state.timers.push({callback, ms}),
    $: html => {
      if (html === '#toastBox') return {append() {}};
      const toast = {html, styles: {}, classes: [], removed: false,
        find: () => ({text: value => { toast.message = value; }}),
        css: (key, value) => { toast.styles[key] = value; },
        addClass: value => toast.classes.push(value), remove: () => {toast.removed = true;}};
      state.toasts.push(toast);
      return toast;
    }
  });
  runInContext(readFileSync(join(__dirname, '../../resources/modules/alerts/public/alertToast.js'), 'utf8'), context);
  return {state, context};
}

test('Toast respeta duración, sincroniza progreso y no interpreta HTML del mensaje', () => {
  const {state, context} = setup();
  runInContext(`alertToast({title: '<img onerror="mal()">', timer: 1700, icon: 'error'});`, context);
  assert.equal(state.timers[0].ms, 1700);
  assert.equal(state.toasts[0].styles['--gframe-toast-duration'], '1700ms');
  assert.equal(state.toasts[0].message, '<img onerror="mal()">');
  assert.equal(state.toasts[0].html.includes('onerror'), false);
  state.timers[0].callback();
  assert.equal(state.toasts[0].removed, true);
  runInContext('alertToast({timer: 0});', context);
  assert.equal(state.timers.length, 1);
  assert.equal(state.toasts[1].classes.includes('gtoast-persistent'), true);
  runInContext('alertToast({timer: -1});', context);
  assert.equal(state.timers[1].ms, 5000);
});

test('SweetAlert conserva clases Bootstrap, opciones y callbacks sin mutar el objeto recibido', async () => {
  const {state, context} = setup();
  await runInContext(`
    const customClass = Object.freeze({confirmButton: 'btn btn-danger'});
    const callback = () => true;
    const supplied = Object.freeze({customClass, preConfirm: callback, showCancelButton: true});
    swalAlert(supplied);
  `, context);
  const options = state.calls[0];
  assert.equal(options.customClass.confirmButton, 'btn btn-danger');
  assert.equal(options.customClass.cancelButton, 'btn btn-secondary');
  assert.equal(options.reverseButtons, true);
  assert.equal(options.preConfirm(), true);
  assert.equal(runInContext('Object.keys(customClass).length', context), 1);
  await runInContext('swalAlert({});', context);
  assert.equal(state.calls[1].customClass.confirmButton, 'btn btn-primary');
  runInContext(`mergeDeep({}, JSON.parse('{"__proto__":{"polluted":true}}'));`, context);
  assert.equal(runInContext('({}).polluted', context), undefined);
});

test('Puente visual conserva spinner visible y usa variables Bootstrap', () => {
  const css = readFileSync(join(__dirname, '../../resources/modules/sweetalert2/public/sweetTheme.css'), 'utf8');
  const toastCss = readFileSync(join(__dirname, '../../resources/modules/alerts/public/alertToast.css'), 'utf8');
  assert.match(css, /\.swal-loading-popup \.swal2-actions\s*\{\s*display: flex/);
  assert.match(css, /\.swal2-actions\s*\{\s*justify-content: center/);
  assert.match(toastCss, /var\(--bs-body-bg\)/);
  assert.equal(toastCss.includes('data-gf-theme'), false);
});
