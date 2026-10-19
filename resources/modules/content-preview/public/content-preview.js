(function (window, document) {
  'use strict';
  const instances = new WeakMap();
  function mount(root) {
    if (!root?.dataset || !root.isConnected) return null;
    if (instances.has(root)) return instances.get(root);
    let url;
    try { url = new URL(root.dataset.stateUrl, window.location.href); } catch (_) { return null; }
    if (!root.dataset.stateUrl || url.origin !== window.location.origin || !root.dataset.version) return null;
    let stopped = false, timer = null, busy = false, controller = null;
    const status = root.querySelector('[data-preview-status]');
    function schedule() { if (!stopped && !document.hidden) timer = window.setTimeout(check, 3000); }
    async function check() {
      if (stopped || busy || document.hidden || !root.isConnected) return;
      busy = true; controller = new AbortController();
      const timeout = window.setTimeout(() => controller.abort(), 10000);
      try {
        const response = await window.fetch(url.href, {credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal: controller.signal});
        if (!response.ok) throw new Error('preview_failed');
        const result = await response.json();
        if (stopped) return;
        if (result.status !== 'success' || typeof result.data?.version !== 'string') throw new Error('preview_failed');
        if (result.data.version !== root.dataset.version) { stopped = true; window.location.reload(); }
      } catch (_) {
        if (!stopped && status) { status.textContent = root.dataset.error; status.hidden = false; }
        stopped = true;
      } finally { window.clearTimeout(timeout); busy = false; schedule(); }
    }
    function visibility() { window.clearTimeout(timer); if (!document.hidden) check(); }
    const instance = {destroy() { stopped = true; window.clearTimeout(timer); controller?.abort(); document.removeEventListener('visibilitychange', visibility); instances.delete(root); }};
    instances.set(root, instance); document.addEventListener('visibilitychange', visibility); schedule(); return instance;
  }
  window.ContentPreview = {mount};
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-content-preview]').forEach(mount));
  else document.querySelectorAll('[data-content-preview]').forEach(mount);
})(window, document);
