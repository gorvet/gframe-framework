const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {Script} = require('node:vm');

const root = join(__dirname, '../..');
const modules = ['bootstrap', 'jquery', 'jquery-ui', 'sweetalert2', 'tinymce',
  'aos', 'chartjs', 'coloris', 'flatpickr', 'html2canvas', 'intl-tel-input',
  'luxon', 'owl-carousel', 'purecounter', 'swiper', 'venobox'];

for (const name of modules) {
  test(name + ': ejemplos válidos, fuente oficial y versión del manifiesto', () => {
    const guide = readFileSync(join(root, 'docs', name + '.md'), 'utf8');
    const manifest = readFileSync(join(root, 'resources/modules', name, 'module.php'), 'utf8');
    const homepage = manifest.match(/'homepage'\s*=>\s*'([^']+)'/)[1];
    assert.ok(guide.includes(homepage), homepage);
    const version = manifest.match(/'version'\s*=>\s*'([^']+)'/);
    if (version) assert.ok(guide.includes(version[1]), version[1]);
    for (const block of guide.matchAll(/```js\r?\n([\s\S]*?)```/g)) {
      assert.doesNotThrow(() => new Script(block[1], {filename: name + '.md'}));
    }
  });
}
