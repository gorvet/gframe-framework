// Helper utilities shared by multiple modules.

function getNewURL(ixOF) {
  var currentURL = window.location.href;
  var cleanUrl = currentURL.split('?')[0];
  var parts = cleanUrl.split('/');
  return parts.slice(0, parts.indexOf(ixOF)).join('/');
}

function stringToURL(texto, typing) {
  var keepTrailingDash = (typeof typing === 'undefined') ? true : !!typing;
  var slug = String(texto || '')
    .replace(/[ñÑ]/g, function(letter) { return letter === 'Ñ' ? 'N' : 'n'; })
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+/, '');

  return keepTrailingDash ? slug : slug.replace(/-+$/, '');
}

function createAutoSlug(sourceSelector, slugSelector, options) {
  var source = document.querySelector(sourceSelector);
  var slug = document.querySelector(slugSelector);
  var placeholderPattern = (options || {}).placeholderPattern || /^sin-titulo(?:-\d+)?$/;
  var locked = false;

  function reset() {
    var currentSlug = String(slug ? slug.value : '').trim();
    locked = currentSlug !== '' && !placeholderPattern.test(currentSlug);
  }

  function sync() {
    if (!source || !slug || locked) return;
    var generated = stringToURL(source.value, false);
    if (generated !== '' || !placeholderPattern.test(slug.value)) slug.value = generated;
  }

  if (!source || !slug) {
    return { reset: function() {} };
  }

  reset();
  source.addEventListener('input', sync);
  slug.addEventListener('input', function() {
    var normalized = stringToURL(slug.value, true);
    if (slug.value !== normalized) slug.value = normalized;
    locked = normalized !== '';
    if (!locked) sync();
  });
  slug.addEventListener('blur', function() {
    slug.value = stringToURL(slug.value, false);
    locked = slug.value !== '';
    if (!locked) sync();
  });

  return { reset: reset };
}

function randomNameGen(prefix) {
  var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  var result = String(prefix || '') + '_';
  for (var i = 0; i < 6; i++) {
    var idx = Math.floor(Math.random() * chars.length);
    result += chars.charAt(idx);
  }
  return result;
}
