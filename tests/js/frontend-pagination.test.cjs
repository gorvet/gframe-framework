const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

test('los eventos de paginación solo afectan a su contenedor', () => {
  const selectors = [];
  const context = {
    window: {}, document: {},
    $: () => ({ on(event, selector) { selectors.push(selector); } })
  };
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/frontend-core/public/utils/pagination.js'), 'utf8'), context);
  assert.deepEqual(selectors, [
    '#all_items_pagination .linkeable',
    '#all_items_pagination .next',
    '#all_items_pagination .prev'
  ]);
  assert.equal(context.buildPaginationItems(0, 1, 5).length, 0);
  assert.equal(context.buildPaginationItems(3, 1, 5).join(','), '1,2,3');
});
