const assert = require('node:assert/strict');
const {test} = require('node:test');
const {join} = require('node:path');

test('La subida de medios envía texto legible al toast global y conserva nombres literales', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  try {
    const page = await browser.newPage();
    const root = join(__dirname, '../..');
    await page.setContent('<!doctype html><div id="upload"><input id="file" type="file"><div id="mount"></div></div><form id="tokens"></form><div id="toastBox"></div>');
    for (const file of ['jquery/public/jquery.min.js', 'alerts/public/alertToast.js', 'media-library/javascript/media-library.js']) {
      await page.addScriptTag({path: join(root, 'resources/modules', file)});
    }
    const result = await page.evaluate(() => {
      const library = {
        $mount: $('#mount'), state: {kind: 'images'}, endpoints: {upload: '/unused'},
        uploadMaxMB: 0, tenantID: 0, saveSource: 'library', syncUrlEnabled: false,
        _refreshTokens: () => $('#tokens'), _updateUploadMaxMbValue() {}, resetFilters() {}, load() {}
      };
      $.ajax = options => {options.success({status: 'success'});};
      MediaLibrary.prototype.attachUpload.call(library, {root: '#upload', fileInput: '#file'});
      const messages = [];
      for (const name of ['2.png', '<strong>literal</strong>.png']) {
        const files = new DataTransfer();
        files.items.add(new File(['fixture'], name, {type: 'image/png'}));
        document.getElementById('file').files = files.files;
        $('#file').trigger('change');
        messages.push($('#toastBox .gtoast p').last().text());
      }
      return {messages, strongElements: document.querySelectorAll('#toastBox strong').length};
    });
    assert.deepEqual(result.messages, ['El archivo 2.png se ha guardado.', 'El archivo <strong>literal</strong>.png se ha guardado.']);
    assert.equal(result.strongElements, 0);
  } finally {
    await browser.close();
  }
});
