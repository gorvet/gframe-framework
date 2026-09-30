const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

test('Markdown inline convierte formato y escapa HTML', () => {
  const context = {};
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/markdown/public/markdown.js'), 'utf8'), context);
  assert.equal(context.markdown2Html('*Hola*'), '<strong>Hola</strong>');
  assert.equal(context.markdown2Html('<script>'), '&lt;script&gt;');
  assert.equal(context.html2Markdown('<strong>Hola</strong>'), '*Hola*');
});
