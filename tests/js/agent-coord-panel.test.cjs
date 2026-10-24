const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function panel() {
  const elements = new Map();
  function element() {
    return { value: '', style: {}, children: [], innerHTML: '', textContent: '', scrollHeight: 0,
      scrollTop: 0, clientHeight: 0, addEventListener() {}, focus() {},
      appendChild(child) { this.children.push(child); } };
  }
  const document = {
    getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); },
    createElement: element,
  };
  const html = fs.readFileSync(path.join(__dirname, '../../bin/agent-coord-panel.html'), 'utf8');
  const source = html.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/\npoll\(\);\s*setInterval\(poll, 2000\);/, '');
  const context = vm.createContext({ document, fetch: async () => { throw new Error('sin conexión'); } });
  vm.runInContext(source, context);
  return { context, elements, run: (code) => vm.runInContext(code, context) };
}

test('el panel actualiza mensajes aunque la lista conserve 200 entradas', () => {
  const p = panel();
  p.context.data = { messages: Array.from({ length: 200 }, (_, i) => ({ agent: 'claude', text: String(i), at: '' })), claims: [] };
  p.run('render(data)');
  p.context.data.messages.shift();
  p.context.data.messages.push({ agent: 'codex', text: 'mensaje nuevo', at: '' });
  p.run('render(data)');
  assert.match(p.elements.get('feed').children.at(-1).innerHTML, /mensaje nuevo/);
});

test('un fallo del servidor conserva el borrador y muestra el error', async () => {
  const p = panel();
  p.elements.get('text').value = 'mensaje pendiente';
  p.context.fetch = async () => ({ ok: false, json: async () => ({ ok: false, error: 'Canal no disponible' }) });
  await p.run('send()');
  assert.equal(p.elements.get('text').value, 'mensaje pendiente');
  assert.equal(p.elements.get('stamp').textContent, 'Canal no disponible');
});

test('un fallo al leer el estado se muestra sin anunciar actualización', async () => {
  const p = panel();
  p.context.fetch = async () => ({ ok: false, json: async () => ({ error: 'Canal corrupto' }) });
  await p.run('poll()');
  assert.equal(p.elements.get('stamp').textContent, 'Canal corrupto');
});
