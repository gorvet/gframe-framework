const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { Script, runInNewContext } = require('node:vm');

const html = readFileSync(join(__dirname, '../../resources/skeleton/public/css/colores.html'), 'utf8');
const source = html.match(/<script>([\s\S]*?)<\/script>/)[1];

test('el visor conserva grupos y copia del original, con selector Bootstrap', () => {
  new Script(source);
  for (const id of ['gPrimary', 'gShadows', 'gType', 'copyAll', 'tLight', 'tDark', 'status']) assert.ok(html.includes(`id="${id}"`));
  assert.ok(html.includes('btn btn-primary'));
  assert.ok(!html.includes('data-gf-theme'));
});

test('las variables nuevas se detectan y agrupan sin catálogo manual', () => {
  const names = ['--bs-primary-rgb', '--bs-border-radius', '--custom-shadow', '--ui-card', '--new-token'];
  const result = runInNewContext(source.slice(0, source.indexOf('    $("tLight").addEventListener')) + '\ndiscoverVariables(); GROUPS;', {
    document: { documentElement: {} },
    getComputedStyle: () => names
  });
  assert.ok(result.find(group => group.key === 'Primary').vars.includes('--bs-primary-rgb'));
  assert.ok(result.find(group => group.key === 'Surfaces').vars.includes('--bs-border-radius'));
  assert.ok(result.find(group => group.key === 'Shadows').vars.includes('--custom-shadow'));
  assert.ok(result.find(group => group.key === 'Other').vars.includes('--new-token'));
  assert.ok(!result.some(group => group.vars.includes('--ui-card')));
});
