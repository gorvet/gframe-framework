const assert = require('node:assert/strict');
const {test} = require('node:test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');

test('Auth y errores conservan los estilos comunes y el footer dentro del viewport', {
  skip: !process.env.GFRAME_TEST_BROWSER
}, async () => {
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  const root = join(__dirname, '../..');
  const read = file => readFileSync(join(root, file), 'utf8');
  const html = source => source.replace(/<\?php[\s\S]*?\?>/g, '').replace(/<\?=[\s\S]*?\?>/g, '#');
  const footer = '<footer id="footer" class="gframe-footer"><div class="footer-credits"><div class="copyright"><div class="container py-3">© Proyecto</div></div><div class="credits"><div class="container py-3">GFrame</div></div></div></footer>';
  try {
    const page = await browser.newPage();
    for (const theme of ['light', 'dark']) {
      for (const viewport of [{width: 1366, height: 768}, {width: 390, height: 844}, {width: 1280, height: 320}]) {
        await page.setViewportSize(viewport);
        for (const view of ['authLogin', 'authRegister', 'authRecovery', 'authReset', 'error']) {
          const isError = view === 'error';
          const module = isError ? 'error-pages' : 'auth-ui';
          const template = html(read(`resources/modules/${module}/application/app/views/templates/${isError ? 'error' : 'auth'}Template.php`).replace('<?= $content ?>', '__CONTENT__'));
          const content = isError
            ? html(read('resources/modules/error-pages/application/app/views/error-pages/_errorCard.php')).replace(/#(?=<\/)/g, 'Ejemplo')
            : html(read(`resources/modules/auth-ui/application/app/views/auth-ui/${view}.php`));
          const css = [read('resources/modules/bootstrap/public/css/bootstrap.min.css'), read('resources/skeleton/public/css/variables.css'), read('resources/skeleton/public/css/common.css'), read(`resources/modules/${module}/public/${isError ? '404' : 'auth'}.css`)].join('\n');
          await page.setContent(`<!doctype html><html data-bs-theme="${theme}"><head><style>${css}</style></head><body class="tpl-${isError ? 'error' : 'auth'}">${template.replace('__CONTENT__', content)}${footer}</body></html>`);
          const state = await page.evaluate(() => {
            const footer = document.getElementById('footer');
            const card = document.querySelector('.login-card');
            const label = document.querySelector('.form-label');
            const sample = document.createElement('label');
            sample.className = 'form-label';
            document.body.appendChild(sample);
            const reference = getComputedStyle(sample);
            const labelStyle = label ? getComputedStyle(label) : null;
            const result = {
              footerBottom: footer.getBoundingClientRect().bottom,
              scrollHeight: document.documentElement.scrollHeight,
              background: getComputedStyle(footer).backgroundColor,
              border: card ? getComputedStyle(card).borderTopWidth : null,
              label: labelStyle ? [labelStyle.color, labelStyle.fontSize, labelStyle.fontWeight] : null,
              reference: [reference.color, reference.fontSize, reference.fontWeight],
              link: document.querySelector('.auth-home-link')?.textContent.trim()
            };
            sample.remove();
            return result;
          });
          if (!isError) {
            assert.deepEqual(state.label, state.reference, `${view}: common labels`);
            assert.equal(state.border, '0px');
            assert.match(state.link, /Ir al inicio/);
            const linkColors = await page.locator('.auth-home-link').evaluate(el => {
              const sample = document.createElement('span');
              sample.style.color = 'var(--bs-primary)';
              el.appendChild(sample);
              const colors = [getComputedStyle(el).color, getComputedStyle(sample).color];
              sample.remove();
              return colors;
            });
            assert.equal(linkColors[0], linkColors[1]);
            if (viewport.height > 320) {
              await page.locator('.auth-home-link').hover();
              const hoverColors = await page.locator('.auth-home-link').evaluate(el => {
                const sample = document.createElement('span');
                sample.style.color = 'var(--bs-primary-hover)';
                el.appendChild(sample);
                const colors = [getComputedStyle(el).color, getComputedStyle(sample).color];
                sample.remove();
                return colors;
              });
              assert.equal(hoverColors[0], hoverColors[1]);
              await page.mouse.move(0, 0);
            }
          }
          assert.equal(state.background, 'rgba(0, 0, 0, 0)');
          if (viewport.height > 320) {
            assert.ok(state.scrollHeight <= viewport.height + 1, `${view}, ${theme}, ${viewport.width}: ${state.scrollHeight}`);
            assert.ok(state.footerBottom <= viewport.height + 1);
          } else {
            assert.ok(state.footerBottom <= state.scrollHeight + 1, 'El footer sigue accesible al desplazarse');
          }
        }
      }
    }
  } finally {
    await browser.close();
  }
});
