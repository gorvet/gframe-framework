// Encuadre de captura: el margen queda dentro del PNG, sin editar la imagen.
module.exports = async function captureFrame(page, selector, file) {
    const selected = process.env.GFRAME_CAPTURE_FILES?.split(',');
    if (selected && !selected.includes(require('node:path').basename(file))) return;
    const target = page.locator(selector).first();
    const originalStyle = await target.getAttribute('style');
    // Mover un iframe reinicia su documento. En el editor se amplía el espacio
    // del elemento existente y se conserva intacta la instancia de TinyMCE.
    if (await target.locator('iframe').count()) {
        const previous = await target.getAttribute('style');
        await target.evaluate(el => el.style.setProperty('padding', '32px', 'important'));
        await page.evaluate(() => new Promise(done => requestAnimationFrame(() => requestAnimationFrame(done))));
        await target.screenshot({path:file, animations:'disabled'});
        await target.evaluate((el, style) => style === null ? el.removeAttribute('style') : el.setAttribute('style', style), previous);
        return;
    }
    await target.evaluate(element => {
        const width = element.getBoundingClientRect().width;
        const frame = document.createElement('div');
        frame.dataset.captureFrame = '';
        Object.assign(frame.style, {padding:'32px', boxSizing:'border-box', display:'flow-root', background:getComputedStyle(document.body).backgroundColor});
        if (element === document.body) {
            element.style.padding = '32px';
            element.dataset.captureFrame = '';
            return;
        }
        frame.style.width = Math.ceil(width + 64) + 'px';
        frame.style.maxWidth = '100%';
        element.style.setProperty('width', width + 'px', 'important');
        element.style.maxWidth = '100%';
        element.before(frame);
        frame.append(element);
    });
    await page.evaluate(() => {
        const select = window.GFSelect?.openInstance;
        if (!select) return;
        select.positionMenu();
        const frame = document.querySelector('[data-capture-frame]');
        const menu = select.menu.getBoundingClientRect();
        const bounds = frame.getBoundingClientRect();
        frame.style.minHeight = Math.ceil(Math.max(bounds.height, menu.bottom - bounds.top + 32)) + 'px';
    });
    await page.evaluate(() => Promise.all([...document.images].filter(i => !i.loading || i.loading !== 'lazy').map(i => i.decode().catch(() => {}))));
    await page.locator('[data-capture-frame]').screenshot({path:file, animations:'disabled'});
    await target.evaluate((el, style) => style === null ? el.removeAttribute('style') : el.setAttribute('style', style), originalStyle);
    await page.evaluate(() => {
        const frame = document.querySelector('[data-capture-frame]');
        if (frame === document.body) { frame.style.removeProperty('padding'); delete frame.dataset.captureFrame; }
        else frame.replaceWith(...frame.childNodes);
    });
};
