const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

test('el cierre único notifica otras pestañas incluso si localStorage está bloqueado', async () => {
  let click, posted, channel, stopped;
  const messages = [], redirects = [], notices = [];
  const document = { addEventListener() {} };
  function $(target) { return { on(event, selector, fn) { assert.equal(selector, '[data-gf-logout]'); click = fn; }, first() { return { length: 1, serialize: () => 'csrfToken=test' }; } }; }
  $.post = (url, data, callback) => { posted = { url, data }; callback({ status: 'success' }); return { fail() {} }; };
  function BroadcastChannel() { channel = this; this.postMessage = message => messages.push(message); }
  const window = {
    site_url: 'https://example.test/app/', is_protected: true, BroadcastChannel,
    location: { origin: 'https://example.test', href: 'https://example.test/app/admin?page=2', assign: target => redirects.push(target) },
    addEventListener() {}, swalAlert: options => { notices.push(options); return Promise.resolve({ isConfirmed: true }); },
    GFHeartbeat: { stop: reason => { stopped = reason; }, triggerNow() {} }
  };
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/heartbeat-client/public/session.js'), 'utf8'), {
    jQuery: $, window, document, BroadcastChannel, encodeURIComponent,
    localStorage: { setItem() { throw new Error('blocked'); } }
  });
  click({ preventDefault() {} });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(posted.url, 'https://example.test/app/ajax/logout');
  assert.equal(posted.data, 'csrfToken=test');
  assert.equal(messages[0].type, 'logout');
  assert.equal(stopped, 'logout');
  assert.deepEqual(redirects, ['https://example.test/app/login']);
  assert.equal(typeof channel.onmessage, 'function');
  assert.equal(notices.length, 1);
  assert.equal(notices[0].title, '¿Deseas cerrar la sesión?');
});

for (const scenario of [
  { reason: 'logout', title: 'Tu sesión se cerró.', redirect: 'https://example.test/app/login' },
  { reason: 'expired', title: 'Tu sesión se cerró por inactividad.', redirect: 'https://example.test/app/login?rd=admin' }
]) {
test(`${scenario.reason} de otra pestaña conserva el aviso y el destino correcto`, async () => {
  let channel;
  const notices = [], redirects = [];
  function $(target) { return { on() {} }; }
  function BroadcastChannel() { channel = this; this.postMessage = () => {}; }
  const window = {
    site_url: 'https://example.test/app/', is_protected: true, BroadcastChannel,
    location: { origin: 'https://example.test', href: 'https://example.test/app/admin', assign: target => redirects.push(target) },
    addEventListener() {}, swalAlert: options => { notices.push(options); return Promise.resolve({ isConfirmed: true }); },
    GFHeartbeat: { stop() {}, triggerNow() {} }
  };
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/heartbeat-client/public/session.js'), 'utf8'), {
    jQuery: $, window, document: { addEventListener() {} }, BroadcastChannel, encodeURIComponent,
    localStorage: { setItem() {} }
  });
  channel.onmessage({ data: { type: scenario.reason } });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(notices.length, 1);
  assert.equal(notices[0].title, scenario.title);
  assert.deepEqual(redirects, [scenario.redirect]);
});
}
