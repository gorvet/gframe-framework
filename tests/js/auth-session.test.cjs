const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

test('el cierre único notifica otras pestañas incluso si localStorage está bloqueado', async () => {
  let click, posted, channel, stopped;
  const messages = [], redirects = [];
  const document = { addEventListener() {} };
  function $(target) { return { on(event, selector, fn) { assert.equal(selector, '[data-gf-logout]'); click = fn; }, first() { return { length: 1, serialize: () => 'csrfToken=test' }; } }; }
  $.post = (url, data, callback) => { posted = { url, data }; callback({ status: 'success' }); return { fail() {} }; };
  function BroadcastChannel() { channel = this; this.postMessage = message => messages.push(message); }
  const window = {
    site_url: 'https://example.test/app/', is_protected: true, BroadcastChannel,
    location: { origin: 'https://example.test', href: 'https://example.test/app/admin?page=2', assign: target => redirects.push(target) },
    addEventListener() {}, swalAlert: () => Promise.resolve({ isConfirmed: true }),
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
  assert.deepEqual(redirects, ['https://example.test/app/login?rd=admin%3Fpage%3D2']);
  assert.equal(typeof channel.onmessage, 'function');
});
