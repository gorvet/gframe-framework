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
  // No acoplamos el test al texto del título (es copy variable); basta con que
  // se muestre un aviso con un título no vacío.
  assert.equal(typeof notices[0].title, 'string');
  assert.ok(notices[0].title.length > 0);
});

// El destino de redirección distingue cada motivo; el texto del título es copy
// variable y no se comprueba de forma literal.
for (const scenario of [
  { reason: 'logout', redirect: 'https://example.test/app/login' },
  { reason: 'expired', redirect: 'https://example.test/app/login?rd=admin' }
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
  assert.equal(typeof notices[0].title, 'string');
  assert.ok(notices[0].title.length > 0);
  assert.deepEqual(redirects, [scenario.redirect]);
});
}
