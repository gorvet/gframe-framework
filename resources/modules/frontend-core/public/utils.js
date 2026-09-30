// utils.js
// Loader de utilidades. Se incluye solo en las vistas que lo necesitan.
(function() {
  if (window.__gfUtilsEntryLoaded) {
    return;
  }
  window.__gfUtilsEntryLoaded = true;
  window.__gfUtilsReady = false;
  window.__gfUtilsErrors = [];

  var files = [
    'utils/helpers.js',
    'utils/errors.js',
    'utils/forms.js',
    'utils/pagination.js'
  ];

  function resolveBasePath() {
    var script = document.currentScript;
    if (!script || !script.src) {
      return 'public/js/core/';
    }
    return script.src.replace(/utils\.js(?:\?.*)?$/i, '');
  }

  function appendSequentially(srcList, index) {
    if (index >= srcList.length) {
      if (window.__gfUtilsErrors.length) {
        document.dispatchEvent(new CustomEvent('gfutilserror', {
          detail: { files: window.__gfUtilsErrors.slice() }
        }));
      } else {
        window.__gfUtilsReady = true;
        document.dispatchEvent(new CustomEvent('gfutilsready'));
      }
      return;
    }

    var src = srcList[index];
    var fileName = src.split('/').pop() || src;
    if (document.querySelector('script[data-gf-utils-file="' + fileName + '"]')) {
      appendSequentially(srcList, index + 1);
      return;
    }

    var s = document.createElement('script');
    s.src = src;
    s.async = false;
    s.setAttribute('data-gf-utils-file', fileName);
    s.onload = function() {
      appendSequentially(srcList, index + 1);
    };
    s.onerror = function() {
      window.__gfUtilsErrors.push(src);
      appendSequentially(srcList, index + 1);
    };

    var currentScript = document.currentScript;
    var mount = currentScript && currentScript.parentNode
      ? currentScript.parentNode
      : (document.body || document.head || document.documentElement);

    if (currentScript && currentScript.nextSibling) {
      mount.insertBefore(s, currentScript.nextSibling);
      return;
    }

    mount.appendChild(s);
  }

  var base = resolveBasePath();
  var sources = files.map(function(path) { return base + path; });

  appendSequentially(sources, 0);
})();
