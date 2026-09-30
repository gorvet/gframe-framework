const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

test('MediaField conserva IDs únicos al leer JSON, CSV y valores simples', () => {
  const window = {};
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/media-library/javascript/media-field.js'), 'utf8'), {
    window, document: {}, jQuery: callback => {
      if (typeof callback === 'function') return;
      return { find: () => ({ addBack: () => ({ each() {} }) }) };
    }
  });
  assert.equal(window.MediaField.normalizeIds('[1,2,1]').join(','), '1,2');
  assert.equal(window.MediaField.normalizeIds('3,4,3').join(','), '3,4');
  assert.equal(window.MediaField.normalizeIds('0,-1,5').join(','), '5');
});
