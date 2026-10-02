const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {runInNewContext} = require('node:vm');

test('El buscador original normaliza, tolera errores y distingue resultados', () => {
  const window = {};
  runInNewContext(readFileSync(join(__dirname, '../../resources/modules/lexical-search/public/lexical-search.js'), 'utf8'), {window});
  const search = window.GFrameLexicalSearch;
  assert.equal(search.normalize('GESTIÓN y campañas!'), 'gestion y campanas');
  assert.equal(search.matches('campañs', 'Campañas programadas'), true);
  assert.equal(search.matches('factura', 'Campañas programadas'), false);
  assert.equal(search.matches('', 'Cualquier contenido'), true);
  assert.equal(search.score('contrato', 'Contrato'), 1);
  assert.equal(window.KnowledgeLexicalSearch, undefined);
});
