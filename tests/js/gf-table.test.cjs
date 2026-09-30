const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

const source = readFileSync(join(__dirname, '../../resources/modules/gf-table/public/gf-table.js'), 'utf8');
const jquery = () => {};
jquery.fn = {};
const context = { $: jquery };
runInNewContext(source, context);

test('interpreta coma decimal y separadores de miles', () => {
  assert.equal(context.gfTableNumber('1.234,56 €'), 1234.56);
  assert.equal(context.gfTableNumber('$1,234.56'), 1234.56);
  assert.equal(context.gfTableNumber('-12,5'), -12.5);
  assert.equal(context.gfTableNumber('1,234', '.'), 1234);
  assert.equal(context.gfTableNumber('1.234', ','), 1234);
});

test('conserva los contratos de corrección de orden, reset e inserción', () => {
  assert.match(source, /type === 'number' \|\| type === 'date'/);
  assert.match(source, /sortTable\(currentSort.column, type, true\)/);
  assert.match(source, /clearTimeout\(sortTimeout\)/);
  assert.match(source, /originalRows.filter/);
  assert.match(source, /\$\(row\).show\(\)/);
  assert.match(source, /filter\('\.gf-table'\).add/);
});
