const assert = require('node:assert/strict');
const {test} = require('node:test');
const {spawn, spawnSync} = require('node:child_process');
const {mkdtempSync, readFileSync, rmSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join, resolve, sep} = require('node:path');
const net = require('node:net');

test('Modales: preselección, WebP, formatos horizontal/vertical, pantalla y temas', {skip: !process.env.GFRAME_TEST_BROWSER, timeout: 90000}, async () => {
  const root = mkdtempSync(join(tmpdir(), 'gframe-media-layout-'));
  const php = process.env.GFRAME_TEST_PHP || 'C:/xampp/php/php.exe';
  // Imágenes sintéticas de distinta proporción, generadas solo en el directorio temporal.
  const generated = spawnSync(php, ['-r', `
    foreach (['landscape' => [1600,900], 'portrait' => [900,1600]] as $name => [$w,$h]) {
      $im=imagecreatetruecolor($w,$h);
      for ($y=0;$y<$h;$y++) imageline($im,0,$y,$w-1,$y,imagecolorallocate($im,40+(int)(90*$y/$h),100+(int)(60*$y/$h),170));
      imagefilledellipse($im,(int)($w*.65),(int)($h*.35),(int)($w*.35),(int)($w*.35),imagecolorallocate($im,230,190,90));
      imagepng($im,$argv[1].'/'.$name.'.png',0); imagedestroy($im);
    }`, root], {windowsHide: true});
  assert.equal(generated.status, 0, generated.stderr?.toString());
  const listener = net.createServer();
  await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
  const port = listener.address().port;
  await new Promise(resolve => listener.close(resolve));
  const server = spawn(php, ['-S', `127.0.0.1:${port}`, join(__dirname, '../fixtures/media-selector.php')], {
    env: {...process.env, GFRAME_MEDIA_TEST_ROOT: root, GFRAME_MEDIA_TEST_IMAGE: join(root, 'landscape.png'), GFRAME_MEDIA_TEST_LEGACY: '1'}, windowsHide: true,
  });
  let logs = '';
  server.stderr.on('data', chunk => logs += chunk.toString());
  const {chromium} = require('playwright');
  const browser = await chromium.launch({headless: true, executablePath: process.env.GFRAME_TEST_BROWSER});
  try {
    const page = await browser.newPage({viewport: {width: 1920, height: 1080}});
    const base = `http://127.0.0.1:${port}`;
    for (let i = 0; i < 60; i++) {
      try { if ((await fetch(base)).ok) break; } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    await page.goto(base);
    const upload = await page.request.post(base + '/ajax/admin/media/upload', {multipart: {file: {
      name: 'Retrato de prueba con un nombre de archivo largo.png', mimeType: 'image/png', buffer: readFileSync(join(root, 'portrait.png')),
    }}});
    const created = await upload.json();
    assert.equal(created.status, 'success');
    const portraitID = created.data.media_id;
    const originalDetails = await (await page.request.post(base + '/ajax/admin/media/details', {form: {media_id: portraitID}})).json();
    assert.match(originalDetails.data.media_url, /\.webp(?:\?|$)/);
    assert.match(originalDetails.data.original_url, /\.png(?:\?|$)/);
    const optimized = await page.request.get(originalDetails.data.media_url);
    assert.equal(optimized.headers()['content-type'], 'image/webp');
    assert.ok((await optimized.body()).length < originalDetails.data.metadata.original.size_bytes);
    const modal = page.locator('#mediaPickerModal');
    await page.evaluate(id => {
      window.pickerShown = false;
      $('#mediaPickerModal').one('shown.bs.modal', () => window.pickerShown = true);
      MediaPicker.open({kind: 'images', selected: [id]});
    }, portraitID);
    await modal.waitFor({state: 'visible'});
    await page.waitForFunction(() => window.pickerShown);
    await page.waitForFunction(() => !document.querySelector('#mp-original-name').disabled);
    assert.equal(await modal.locator('#mp-original-name').inputValue(), originalDetails.data.original_name);
    const box = await modal.locator('.modal-content').boundingBox();
    assert.ok(box.width >= 1880 && box.height >= 1040, 'El modal aprovecha el viewport.');
    await page.route('**/ajax/admin/media/details', async route => {
      const response = await route.fetch(); await new Promise(resolve => setTimeout(resolve, 350)); await route.fulfill({response});
    });
    await modal.locator('[data-media-id="1"]').click();
    await page.waitForTimeout(100);
    assert.deepEqual(await modal.locator('.modal-content').boundingBox(), box);
    await page.waitForFunction(() => document.querySelector('#mp-original-name').value === 'Primera.png');
    await modal.locator('[data-mp-preview]').evaluate(image => image.decode());
    assert.deepEqual(await modal.locator('.modal-content').boundingBox(), box);
    const widths = await modal.locator('.media-library-item').evaluateAll(items => items.map(item => item.getBoundingClientRect().width));
    assert.ok(widths.every(width => Math.abs(width - widths[0]) < 1), 'Los nombres largos no ensanchan las miniaturas.');
    // Un registro antiguo conserva su ID, original y miniaturas al optimizar desde el selector.
    const before = await (await page.request.post(base + '/ajax/admin/media/details', {form: {media_id: 1}})).json();
    const originalBytes = await (await page.request.get(before.data.original_url)).body();
    await modal.locator('[data-mp-optimize]').click();
    await page.waitForFunction(() => window.lastToast?.title.includes('WebP generada'));
    assert.equal(await modal.locator('[data-mp-optimize]').isVisible(), false);
    assert.match(await modal.locator('#mp-file-url').inputValue(), /\.webp(?:\?|$)/);
    const after = await (await page.request.post(base + '/ajax/admin/media/details', {form: {media_id: 1}})).json();
    assert.equal(after.data.media_id, 1);
    assert.equal(after.data.original_url, before.data.original_url);
    assert.deepEqual(await (await page.request.get(after.data.original_url)).body(), originalBytes);
    const screenshots = process.env.GFRAME_TEST_SCREENSHOTS;
    if (screenshots) await page.screenshot({path: join(screenshots, 'media-modal-picker-wide.png')});
    await modal.locator('[data-media-id="' + portraitID + '"]').click();
    await page.waitForFunction(name => document.querySelector('#mp-original-name').value === name, originalDetails.data.original_name);
    assert.deepEqual(await modal.locator('.modal-content').boundingBox(), box);
    await page.unroute('**/ajax/admin/media/details');
    for (const viewport of [{width: 1280, height: 720}, {width: 768, height: 1024}, {width: 390, height: 844}]) {
      await page.setViewportSize(viewport);
      assert.ok(await modal.evaluate(el => el.scrollWidth <= el.clientWidth + 1));
      const footer = await modal.locator('.modal-footer').boundingBox();
      assert.ok(footer.y + footer.height <= viewport.height + 1);
      await modal.locator('#mp-alt-text').scrollIntoViewIfNeeded();
      assert.equal(await modal.locator('#mp-alt-text').isVisible(), true);
      await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'dark'));
      if (screenshots) await page.screenshot({path: join(screenshots, `media-modal-picker-${viewport.width}.png`)});
    }
    await modal.locator('[data-bs-dismiss]').first().click();
    await modal.waitFor({state: 'hidden'});
    await page.setViewportSize({width: 1920, height: 1080});
    await page.goto(base + '/admin?source=library');
    const detail = page.locator('#editMediaModal');
    await page.locator('#knowledgeMediaList [data-media-id="' + portraitID + '"]').click();
    await detail.waitFor({state: 'visible'});
    await page.waitForTimeout(350);
    await page.waitForFunction(() => !document.querySelector('#media-original-name').disabled);
    await detail.locator('#img_details').evaluate(image => image.decode());
    const detailBox = await detail.locator('.modal-content').boundingBox();
    assert.ok(detailBox.width >= 1880 && detailBox.height >= 1040);
    assert.ok((await detail.locator('#img_details').boundingBox()).height > 800, 'La vista previa aprovecha la altura.');
    if (screenshots) await page.screenshot({path: join(screenshots, 'media-modal-details-wide.png')});
    await detail.locator('#editMediaNext').click();
    await page.waitForFunction(() => document.querySelector('#media_id').value === '2' && !document.querySelector('#media-original-name').disabled);
    assert.deepEqual(await detail.locator('.modal-content').boundingBox(), detailBox);
    assert.equal(await detail.locator('#media-optimize').isVisible(), true);
    await page.evaluate(() => window.lastToast = undefined);
    await detail.locator('#media-optimize').click();
    await page.waitForFunction(() => window.lastToast?.title.includes('WebP generada'));
    await page.waitForFunction(() => document.querySelector('#img_url').value.includes('.webp'));
    assert.equal(await detail.locator('#media-optimize').isVisible(), false);
    await page.setViewportSize({width: 390, height: 844});
    await detail.locator('#alt_text').scrollIntoViewIfNeeded();
    assert.ok(await detail.evaluate(el => el.scrollWidth <= el.clientWidth + 1));
    const footer = await detail.locator('.modal-footer').boundingBox();
    assert.ok(footer.y + footer.height <= 845);
    if (screenshots) await page.screenshot({path: join(screenshots, 'media-modal-details-mobile.png')});
  } catch (error) {
    error.message += '\nPHP: ' + logs.slice(-3000); throw error;
  } finally {
    await browser.close(); server.kill(); await new Promise(resolve => server.once('exit', resolve));
    assert.ok(resolve(root).startsWith(resolve(tmpdir()) + sep));
    rmSync(root, {recursive: true, force: true});
  }
});
