const assert = require('node:assert/strict');
const {test} = require('node:test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');

test('GFSelect conserva las dimensiones y los estados de los campos nativos', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true,
    ...(process.env.GFRAME_TEST_BROWSER !== '1' ? {executablePath: process.env.GFRAME_TEST_BROWSER} : {}),
    ...(process.env.GFRAME_TEST_BROWSER_CHANNEL ? {channel: process.env.GFRAME_TEST_BROWSER_CHANNEL} : {})});
  try {
    const page = await browser.newPage();
    const root = join(__dirname, '../..');
    const markup = readFileSync(join(root, 'tests/fixtures/gfselect-preview.php'), 'utf8')
      .replace(/<\?php[\s\S]*?\?>/g, '')
      .replace(/<link[^>]*>/g, '')
      .replace(/<script[\s\S]*?<\/script>/g, '');
    await page.setContent(markup);
    for (const file of [
      'resources/modules/bootstrap/public/css/bootstrap.min.css',
      'resources/skeleton/public/css/variables.css',
      'resources/skeleton/public/css/common.css',
      'resources/modules/gf-select/public/gf-select.css'
    ]) await page.addStyleTag({path: join(root, file)});
    await page.addStyleTag({content: '*, *::before, *::after { transition: none !important; }'});
    await page.addScriptTag({path: join(root, 'resources/modules/gf-select/public/gf-select.js')});
    for (const theme of ['light', 'dark']) {
      for (const size of ['', 'form-select-sm', 'form-select-lg']) {
        for (const disabled of [false, true]) {
          const result = await page.evaluate(({theme, size, disabled}) => {
            document.documentElement.dataset.bsTheme = theme;
            const native = document.getElementById('native');
            const select = document.getElementById('country');
            GFSelect.getInstance(select)?.destroy();
            native.className = select.className = 'form-select ' + size;
            native.disabled = select.disabled = disabled;
            const component = new GFSelect(select, {loadStyles: false});
            document.activeElement?.blur();
            const properties = ['fontSize', 'lineHeight', 'paddingTop', 'paddingBottom', 'paddingLeft', 'borderRadius', 'borderTopWidth', 'borderTopColor', 'backgroundColor', 'color'];
            const appearance = element => {
              const style = getComputedStyle(element);
              return Object.fromEntries(properties.map(name => [name, style[name]]).concat([['height', element.getBoundingClientRect().height]]));
            };
            const initial = [appearance(native), appearance(component.toggle)];
            if (!disabled) {
              native.focus();
              const nativeFocus = appearance(native);
              component.toggle.focus();
              return {initial, focus: [nativeFocus, appearance(component.toggle)]};
            }
            return {initial};
          }, {theme, size, disabled});
          assert.deepEqual(result.initial[1], result.initial[0], `${theme}, ${size}, disabled=${disabled}`);
          if (result.focus) assert.deepEqual(result.focus[1], result.focus[0], `focus: ${theme}, ${size}`);
        }
      }
    }
    await page.evaluate(() => {
      const component = GFSelect.getInstance('#country');
      component.select.disabled = false;
      component.refresh();
      component.openMenu();
    });
    assert.equal(await page.locator('.gf-select-search-input').evaluate(el => el.classList.contains('form-control')), true);
    await page.locator('.gf-select-search-input').fill('España');
    assert.equal(await page.locator('.gf-select-option:not([hidden])').count(), 1);
    const states = await page.evaluate(() => {
      const component = GFSelect.getInstance('#country');
      component.closeMenu();
      const closed = getComputedStyle(component.menu).display;
      component.openMenu();
      const open = getComputedStyle(component.menu).display;
      const multi = new GFSelect('#multi', {loadStyles: false});
      multi.selectOption(0);
      multi.selectOption(1);
      multi.selectOption(2);
      component.select.disabled = true;
      component.refresh();
      return {closed, open, disabledClosed: !component.isOpen(), selected: multi.getValue().length};
    });
    assert.deepEqual(states, {closed: 'none', open: 'block', disabledClosed: true, selected: 2});
  } finally {
    await browser.close();
  }
});
