const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

function setup(script, search = '') {
  const handlers = new Map(), requests = [], fields = new Map();
  let valid = true, feedback = 0, passwordBindings = 0;
  const window = { location: { search, href: '' } };
  const form = { checkValidity: () => valid };
  function $(selector) {
    return {
      0: form,
      on(event, target, callback) {
        handlers.set(typeof target === 'function' ? `${String(selector)}:${event}` : target,
          typeof target === 'function' ? target : callback);
        return this;
      },
      serialize: () => 'middle_name=&login_email=user%40example.test',
      addClass() { return this; },
      val(value) {
        if (value !== undefined) fields.set(selector, value);
        return fields.get(selector) || '';
      }
    };
  }
  $.ajax = options => requests.push(options);
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/auth-ui/javascript', script), 'utf8'), {
    jQuery: $, window, document: {}, site_url: 'https://example.test/app/', URL, URLSearchParams,
    localStorage: { removeItem() {} },
    validationFeedback: () => feedback++, showPassword: () => passwordBindings++,
    showSpinner() {}, generatePassword: () => 'Password-123', updateMeterPassword() {}, passwordValidate: () => true,
    swalAlert: () => Promise.resolve({ isConfirmed: false }), alertToast() {},
    successError: () => ({ title: '' }), ajaxError: () => ({ title: '' })
  });
  return { handlers, requests, fields, window, invalidate: () => { valid = false; },
    feedback: () => feedback, passwordBindings: () => passwordBindings };
}

for (const [script, button, path] of [
  ['AuthLogin.js', '#submit_login', 'ajax/login'],
  ['AuthRegister.js', '#submit_register', 'ajax/register'],
  ['AuthLostpassword.js', '#submit_recovery', 'ajax/lostpassword'],
  ['AuthResetpassword.js', '#submit_reset', 'ajax/resetpassword']
]) {
  test(`${script} conecta el botón con su ruta original`, () => {
    const state = setup(script, '?rp=token');
    state.handlers.get(`${button}:click`)({ preventDefault() {}, stopPropagation() {} });
    assert.equal(state.requests.length, 1);
    assert.equal(state.requests[0].url, `https://example.test/app/${path}`);
    if (script === 'AuthResetpassword.js') assert.equal(state.fields.get('#rpuser_token'), 'token');
    if (script !== 'AuthLostpassword.js') assert.equal(state.passwordBindings(), 1);
  });
}

for (const [script, button] of [['AuthLogin.js', '#submit_login'], ['AuthRegister.js', '#submit_register'], ['AuthLostpassword.js', '#submit_recovery']]) {
  test(`${script} valida antes de enviar`, () => {
    const state = setup(script);
    state.invalidate();
    state.handlers.get(`${button}:click`)({ preventDefault() {} });
    assert.equal(state.requests.length, 0);
    assert.equal(state.feedback(), 1);
  });
}

test('login transmite rd y respeta el destino del servidor incluso si exige cambiar contraseña', () => {
  const state = setup('AuthLogin.js', '?rd=admin%2Fitems');
  state.handlers.get('#submit_login:click')({ preventDefault() {} });
  assert.equal(new URLSearchParams(state.requests[0].data).get('rd'), 'admin/items');
  for (const redirect of ['account', 'admin']) {
    state.requests[0].success({ status: 'success', redirect, must_change_password: true });
    assert.equal(state.window.location.href, new URL(redirect, 'https://example.test/app/').href);
  }
});

test('login valida el token y reenvía la verificación con las rutas originales', () => {
  const state = setup('AuthLogin.js', '?v=token');
  assert.equal(state.requests[0].url, 'https://example.test/app/ajax/validateacount');
  assert.equal(new URLSearchParams(state.requests[0].data).get('vtoken'), 'token');
  state.handlers.get('#verifyAcount')({ preventDefault() {}, stopPropagation() {} });
  assert.equal(state.requests[1].url, 'https://example.test/app/ajax/verifyacount');
});

test('login conserva el destino web cuando otra pestaña ya inició sesión', () => {
  const state = setup('AuthLogin.js');
  state.handlers.get('#submit_login:click')({ preventDefault() {} });
  state.requests[0].error({ responseJSON: { code: 'already_logged' } }, 'error', '');
  assert.equal(state.window.location.href, 'https://example.test/app/login/');
});
