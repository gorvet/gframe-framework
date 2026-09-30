(function() {
  "use strict";

  const header = document.querySelector('#header');
  const headerH = () => (header ? header.offsetHeight : 0);
  const headerMeasureWidth = () => Math.round(window.innerWidth || document.documentElement.clientWidth || 0);
  const NAV = document.querySelector('#mng');
  const normalizePath = (path) => (path || '/').replace(/\/+$/, '') || '/';
  const SCROLLED_AT = 50;
  let scrollAnimationFrame = null;
  let headerMeasureCache = {
    width: headerMeasureWidth(),
    unscrolled: headerH(),
    scrolled: 0
  };

  function isDropdownToggleLink(link) {
    if (!link) return false;
    const toggleType = link.getAttribute('data-bs-toggle');
    return link.classList.contains('dropdown-toggle') ||
           toggleType === 'dropdown' ||
           toggleType === 'collapse';
  }

  function getHashId(hash) {
    if (!hash || hash.length <= 1) return '';
    try {
      return decodeURIComponent(hash.slice(1));
    } catch (e) {
      return hash.slice(1);
    }
  }

  function getUrlFromHref(href) {
    try {
      return new URL(href, window.location.href);
    } catch (e) {
      return null;
    }
  }

  function getLocalHashData(link) {
    if (!link || isDropdownToggleLink(link)) return null;

    const raw = (link.getAttribute('href') || '').trim();
    if (!raw || raw === '#') return null;

    const url = getUrlFromHref(raw);
    if (!url || url.origin !== location.origin) return null;

    const id = getHashId(url.hash);
    if (!id) return null;

    const samePage = raw.charAt(0) === '#' || (normalizePath(url.pathname) === normalizePath(location.pathname) && url.search === location.search);
    if (!samePage) return null;

    const target = document.getElementById(id);
    return target ? { id, target } : null;
  }

  function setCurrentLink(link) {
    if (!NAV || !link || !NAV.contains(link)) return;
    NAV.querySelectorAll('a.current').forEach(x => x.classList.remove('current'));
    link.classList.add('current');
  }

  function measureHeaderHeight(scrolled) {
    if (!header) return 0;

    const viewportWidth = headerMeasureWidth();
    const cacheKey = scrolled ? 'scrolled' : 'unscrolled';

    if (headerMeasureCache.width === viewportWidth && headerMeasureCache[cacheKey]) {
      return headerMeasureCache[cacheKey];
    }

    if (!scrolled && document.body.classList.contains('scrolled') && headerMeasureCache.unscrolled) {
      return headerMeasureCache.unscrolled;
    }

    const sandbox = document.createElement('div');
    const clone = header.cloneNode(true);

    if (scrolled) sandbox.className = 'scrolled';
    sandbox.setAttribute('aria-hidden', 'true');
    sandbox.style.position = 'absolute';
    sandbox.style.visibility = 'hidden';
    sandbox.style.pointerEvents = 'none';
    sandbox.style.left = '-10000px';
    sandbox.style.top = '0';
    sandbox.style.width = `${header.getBoundingClientRect().width || window.innerWidth}px`;
    sandbox.style.overflow = 'hidden';

    clone.style.transition = 'none';
    clone.style.animation = 'none';
    clone.style.position = 'static';
    clone.style.width = '100%';

    sandbox.appendChild(clone);
    document.body.appendChild(sandbox);
    const height = clone.offsetHeight;
    sandbox.remove();

    if (headerMeasureCache.width !== viewportWidth) {
      headerMeasureCache = {
        width: viewportWidth,
        unscrolled: document.body.classList.contains('scrolled') ? headerMeasureCache.unscrolled : headerH(),
        scrolled: 0
      };
    }

    headerMeasureCache[cacheKey] = height;

    return height;
  }

  function targetScrollTop(target) {
    if (isTopTarget(target)) return 0;

    const rawTop = target.getBoundingClientRect().top + window.pageYOffset;
    const scrolledHeaderH = measureHeaderHeight(true);
    const finalTopWithScrolledHeader = rawTop - scrolledHeaderH;

    return finalTopWithScrolledHeader > SCROLLED_AT
      ? finalTopWithScrolledHeader
      : rawTop - measureHeaderHeight(false);
  }

  function isTopTarget(target) {
    return target === document.body ||
           target === document.documentElement ||
           target.id === 'hero';
  }

  function animateScrollTo(endTop, duration, onDone) {
    const scroller = document.scrollingElement || document.documentElement;
    const maxTop = Math.max(0, scroller.scrollHeight - window.innerHeight);
    const finalTop = Math.min(Math.max(endTop, 0), maxTop);
    const startTop = window.pageYOffset;
    const distance = finalTop - startTop;
    let startTime = null;

    if (scrollAnimationFrame) cancelAnimationFrame(scrollAnimationFrame);

    function ease(progress) {
      return progress < 0.5
        ? 4 * progress * progress * progress
        : 1 - Math.pow(-2 * progress + 2, 3) / 2;
    }

    function step(timestamp) {
      if (startTime === null) startTime = timestamp;
      const progress = Math.min((timestamp - startTime) / duration, 1);
      window.scrollTo(0, startTop + (distance * ease(progress)));
      if (progress < 1) {
        scrollAnimationFrame = requestAnimationFrame(step);
      } else {
        scrollAnimationFrame = null;
        if (typeof onDone === 'function') onDone();
      }
    }

    scrollAnimationFrame = requestAnimationFrame(step);
  }

  function smoothScrollTo(target) {
    const targetTop = targetScrollTop(target);
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      if (scrollAnimationFrame) cancelAnimationFrame(scrollAnimationFrame);
      scrollAnimationFrame = null;
      window.scrollTo(0, targetTop);
      return;
    }
    const distance = targetTop - window.pageYOffset;

    if (Math.abs(distance) < 2) {
      window.scrollTo(0, targetTop);
      return;
    }

    const duration = Math.min(Math.max(Math.abs(distance) * 0.45, 350), 800);
    animateScrollTo(targetTop, duration, () => alignAfterScroll(target));
  }

  function alignAfterScroll(target) {
    const correctedTop = targetScrollTop(target);
    const delta = Math.abs(correctedTop - window.pageYOffset);
    if (delta > 2) {
      const duration = Math.min(Math.max(delta * 2, 120), 260);
      animateScrollTo(correctedTop, duration);
    }
  }

  function toggleScrolled() {
    const selectHeader = document.querySelector('#header');
    if (!selectHeader) return;
    if (!selectHeader.classList.contains('scroll-up-sticky') &&
        !selectHeader.classList.contains('sticky-top') &&
        !selectHeader.classList.contains('fixed-top')) return;
    document.body.classList.toggle('scrolled', window.scrollY > SCROLLED_AT);
  }
  document.addEventListener('scroll', toggleScrolled);
  window.addEventListener('load', toggleScrolled);

  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');
  let previousOverflow = '';
  function mobileNavToogle() {
    if (document.body.classList.contains('mobile-nav-active')) {
      closeMobileNav();
      return;
    }
    previousOverflow = document.body.style.overflow;
    const body = document.body;
    body.classList.toggle('mobile-nav-active');
    if (mobileNavToggleBtn) {
      mobileNavToggleBtn.classList.toggle('gicon-menu');
      mobileNavToggleBtn.classList.toggle('gicon-close');
      mobileNavToggleBtn.setAttribute('aria-expanded', 'true');
    }
    body.style.overflow = body.classList.contains('mobile-nav-active') ? 'hidden' : '';
  }

  function closeMobileNav() {
    const body = document.body;
    if (!body.classList.contains('mobile-nav-active')) return;

    body.classList.remove('mobile-nav-active');
    if (mobileNavToggleBtn) {
      mobileNavToggleBtn.classList.add('gicon-menu');
      mobileNavToggleBtn.classList.remove('gicon-close');
      mobileNavToggleBtn.setAttribute('aria-expanded', 'false');
    }
    body.style.overflow = previousOverflow;
  }

  if (mobileNavToggleBtn) {
    mobileNavToggleBtn.setAttribute('aria-expanded', String(document.body.classList.contains('mobile-nav-active')));
    mobileNavToggleBtn.addEventListener('click', mobileNavToogle);
  }
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('mobile-nav-active')) {
      closeMobileNav();
      if (mobileNavToggleBtn) mobileNavToggleBtn.focus();
    }
  });

  document.addEventListener('click', (event) => {
    const clickTarget = event.target instanceof Element ? event.target : event.target.parentElement;
    const link = clickTarget ? clickTarget.closest('a[href]') : null;
    if (!header || !link || !header.contains(link)) return;

    const hashData = getLocalHashData(link);
    if (!hashData) {
      if (NAV && NAV.contains(link) &&
          !isDropdownToggleLink(link) &&
          (link.getAttribute('href') || '').trim() !== '#' &&
          document.body.classList.contains('mobile-nav-active')) {
        closeMobileNav();
      }
      return;
    }

    event.preventDefault();
    if (document.body.classList.contains('mobile-nav-active')) closeMobileNav();

    smoothScrollTo(hashData.target);
    setCurrentLink(link);
    history.replaceState(null, '', location.pathname + location.search);
  }, true);

  document.addEventListener('click', (event) => {
    if (!document.body.classList.contains('mobile-nav-active')) return;

    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    if (target.closest('#mng .navbar-nav') || target.closest('.mobile-nav-toggle')) return;

    closeMobileNav();
  });

  window.addEventListener('load', () => {
    const loadHashId = getHashId(location.hash);
    if (loadHashId) {
      const target = document.getElementById(loadHashId);
      if (target) {
        setTimeout(() => {
          smoothScrollTo(target);
          history.replaceState(null, '', location.pathname + location.search);

          if (NAV) {
            const link = Array.from(NAV.querySelectorAll('a[href]')).find((item) => {
              const data = getLocalHashData(item);
              return data && data.id === target.id;
            });
            if (link) setCurrentLink(link);
          }
        }, 60);
      }
    }

    if (NAV) {
      NAV.querySelectorAll('a[href]').forEach(a => {
        if (getLocalHashData(a)) a.classList.remove('current');
      });
    }
  });

  const anchors = [];
  if (NAV) {
    NAV.querySelectorAll('a[href^="#"], a[href*="#"]').forEach(a => {
      const data = getLocalHashData(a);
      if (!data) return;
      const id = data.id;
      const el = data.target;
      if (id.startsWith('sm_') || el.closest('.dropdown-menu')) return;
      anchors.push({ id, link: a, el });
    });
  }

  if (anchors.length && typeof IntersectionObserver === 'function') {
    const io = new IntersectionObserver((entries) => {
      let best = null;
      entries.forEach(en => {
        if (en.isIntersecting) {
          if (!best || en.intersectionRatio > best.intersectionRatio) best = en;
        }
      });
      if (!best) return;
      const id = best.target.id;
      const active = anchors.find(x => x.id === id)?.link;
      if (!active) return;
      if (NAV) NAV.querySelectorAll('a.current').forEach(x => x.classList.remove('current'));
      active.classList.add('current');
    }, {
      threshold: [0.1, 0.25, 0.5],
      rootMargin: `-${headerH() + 10}px 0px -55% 0px`
    });

    anchors.forEach(x => io.observe(x.el));
  }

  const scrollTopBtn = document.querySelector('.scroll-top');
  function toggleScrollTop() {
    if (!scrollTopBtn) return;
    scrollTopBtn.classList.toggle('active', window.scrollY > 100);
  }
  if (scrollTopBtn) {
    scrollTopBtn.addEventListener('click', (e) => {
      e.preventDefault();
      smoothScrollTo(document.body);
    });
  }
  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);
})();
