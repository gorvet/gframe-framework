const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync, existsSync} = require('node:fs');
const {join} = require('node:path');

const root = join(__dirname, '../..');
const css = readFileSync(join(root, 'resources/modules/gframe-icons/public/style.css'), 'utf8');
const guide = readFileSync(join(root, 'docs/gframe-icons.md'), 'utf8');

test('Las clases documentadas existen en el catálogo de iconos', () => {
  const classes = new Set(guide.match(/gicon-[a-zA-Z0-9-]+/g));
  for (const name of classes) assert.ok(css.includes('.' + name), name);
  for (let part = 1; part <= 4; part++) {
    assert.ok(css.includes('.gicon-gcolor .path' + part + ':before'));
  }
});

test('Las fuentes relativas del CSS están presentes en el módulo', () => {
  for (const match of css.matchAll(/url\('([^']+)'\)/g)) {
    const path = match[1].split(/[?#]/)[0];
    assert.ok(existsSync(join(root, 'resources/modules/gframe-icons/public', path)), path);
  }
  assert.match(css, /i\[class\^="gicon-"\]/);
});
