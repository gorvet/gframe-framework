const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

function setup(id, passwordValue) {
  const handlers = new Map(), requests = [], notices = [], redirects = [];
  let validationCalls = 0, meterCalls = 0;
  const password = passwordValue === undefined ? null : { value: passwordValue, validity: '', setCustomValidity(value) { this.validity = value; }, addEventListener() {} };
  const form = {
    id, dataset: {}, action: '/app/ajax/login', classList: { add() {} },
    querySelector: selector => selector.includes('register_password') ? password : null,
    querySelectorAll: () => [], checkValidity: () => !password?.validity
  };
  const document = { querySelector: selector => selector.includes('register_password') ? password : null, getElementById: () => form };
  function $(target) {
    return {
      on: (event, selector, callback) => handlers.set(typeof selector === 'function' ? event : selector, typeof selector === 'function' ? selector : callback),
      find: () => ({ prop() {}, removeClass() {}, addClass() {} }),
      serializeArray: () => [{ name: 'login_email', value: 'user@example.test' }]
    };
  }
  $.param = values => values;
  $.ajax = options => {
    const callbacks = {};
    requests.push({ options, callbacks });
    const chain = { done(fn) { callbacks.done = fn; return chain; }, fail(fn) { callbacks.fail = fn; return chain; }, always(fn) { callbacks.always = fn; return chain; } };
    return chain;
  };
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/auth-ui/javascript/auth.js'), 'utf8'), {
    jQuery: $, document, URLSearchParams,
    window: { site_url: '/app/', location: { search: '?rd=admin%2Fitems', assign: value => redirects.push(value) } },
    validationFeedback: () => validationCalls++, passwordValidate: value => Buffer.byteLength(value) >= 8 && Buffer.byteLength(value) <= 72,
    generatePassword: () => null, updateMeterPassword: () => meterCalls++, showPassword() {},
    swalAlert: options => { notices.push(options); return Promise.resolve({ isConfirmed: false }); },
    successError: message => ({ title: message }), ajaxError: () => ({ title: 'Error público' }), alertToast: options => notices.push(options)
  });
  return { form, handlers, requests, notices, redirects, validationCalls: () => validationCalls, meterCalls: () => meterCalls };
}

test('el acceso transmite rd, evita doble envío y respeta el destino del servidor', () => {
  const state = setup('auth-login-form');
  const submit = state.handlers.get('submit');
  submit.call(state.form, { preventDefault() {} });
  submit.call(state.form, { preventDefault() {} });
  assert.equal(state.requests.length, 1);
  assert.equal(state.requests[0].options.data.find(item => item.name === 'rd').value, 'admin/items');
  state.requests[0].callbacks.done({ status: 'success', redirect: 'account', must_change_password: true });
  assert.deepEqual(state.redirects, ['/app/account']);
  state.requests[0].callbacks.always();
  assert.equal(state.form.dataset.submitting, undefined);
});

test('una contraseña fuera de la política usa validationFeedback y no se envía', () => {
  const state = setup('auth-register-form', 'short');
  state.handlers.get('submit').call(state.form, { preventDefault() {} });
  assert.equal(state.validationCalls(), 1);
  assert.equal(state.requests.length, 0);
  assert.equal(state.meterCalls(), 1);
});

test('already_logged continúa por la ruta web tanto en JSON exitoso como en fallo HTTP', () => {
  for (const transport of ['done', 'fail']) {
    const state = setup('auth-login-form');
    state.handlers.get('submit').call(state.form, { preventDefault() {} });
    if (transport === 'done') state.requests[0].callbacks.done({ status: 'unauthorized', code: 'already_logged' });
    else state.requests[0].callbacks.fail({ responseJSON: { code: 'already_logged' } }, 'error');
    assert.deepEqual(state.redirects, ['/app/login']);
    assert.equal(state.notices.length, 0);
  }
});

test('el reenvío usa la ruta propia y los fallos se presentan sin detalle técnico', () => {
  const state = setup('auth-login-form');
  state.handlers.get('[data-auth-resend]')({ preventDefault() {} });
  assert.equal(state.requests[0].options.url, '/app/ajax/verification');
  assert.equal(state.requests[0].options.data.some(item => item.name === 'rd'), false);
  state.requests[0].callbacks.fail({}, 'error', 'secreto');
  assert.equal(state.notices[0].title, 'Error público');
});
