// ── NAVIGATION LOADING BAR + PREFETCH ──
(function () {
  var bar = document.createElement('div');
  bar.style.cssText = 'position:fixed;top:0;left:0;width:0;height:3px;background:linear-gradient(90deg,#6366f1,#a78bfa,#818cf8);z-index:99999;border-radius:0 2px 2px 0;pointer-events:none;opacity:0;box-shadow:0 0 8px rgba(99,102,241,.55)';
  document.addEventListener('DOMContentLoaded', function () { document.body.appendChild(bar); });

  var timer = null;
  var pct = 0;

  function startBar() {
    clearInterval(timer);
    pct = 0;
    bar.style.transition = 'none';
    bar.style.width = '0';
    bar.style.opacity = '1';
    setTimeout(function () {
      bar.style.transition = 'width .35s ease';
    }, 16);
    timer = setInterval(function () {
      if (pct < 82) {
        pct += Math.max((82 - pct) * 0.12, 0.6);
        bar.style.width = pct + '%';
      }
    }, 120);
  }

  function finishBar() {
    clearInterval(timer);
    bar.style.transition = 'width .18s ease';
    bar.style.width = '100%';
    setTimeout(function () {
      bar.style.transition = 'opacity .3s ease';
      bar.style.opacity = '0';
      setTimeout(function () { bar.style.width = '0'; }, 320);
    }, 180);
  }

  // Trigger bar on internal link click
  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[href]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#') return;
    if (href.indexOf('javascript') === 0 || href.indexOf('mailto') === 0) return;
    if (link.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey) return;
    startBar();
  }, true);

  // Trigger bar on form submit + prevent double-submit
  document.addEventListener('submit', function (e) {
    var form = e.target;

    // Skip forms inside detail modal — handled by inline fetch, no navigation
    if (form.closest('#detailModal')) return;

    // Block if already submitting
    if (form.dataset.submitting === '1') {
      e.preventDefault();
      return;
    }
    form.dataset.submitting = '1';
    startBar();

    // Disable submit button visually
    var btn = form.querySelector('[type="submit"]');
    if (btn && !btn.dataset.noLock) {
      btn.disabled = true;
      var origHtml = btn.innerHTML;
      btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px">'
        + '<svg style="width:14px;height:14px;animation:spin .7s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">'
        + '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>'
        + ' กำลังบันทึก...</span>';

      // Safety unlock after 8s
      setTimeout(function () {
        form.dataset.submitting = '';
        btn.disabled = false;
        btn.innerHTML = origHtml;
      }, 8000);
    }
  }, true);

  // Complete bar when new page appears
  window.addEventListener('pageshow', finishBar);

  // Prefetch .php pages on hover (reduces server round-trip)
  var prefetched = {};
  document.addEventListener('mouseover', function (e) {
    var link = e.target.closest('a[href]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || prefetched[href]) return;
    if (href.indexOf('.php') === -1 && href.charAt(0) !== '/') return;
    prefetched[href] = true;
    var el = document.createElement('link');
    el.rel = 'prefetch';
    el.href = href;
    document.head.appendChild(el);
  }, { passive: true });
})();

document.addEventListener('DOMContentLoaded', function () {
  // Confirm dialogs
  document.querySelectorAll('form[data-confirm], [data-confirm]').forEach(function (el) {
    const message = el.getAttribute('data-confirm');
    if (!message) return;
    el.addEventListener('submit', function (e) { if (!confirm(message)) e.preventDefault(); });
  });

  // Service worker registration
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  }

  // PWA install prompt (Android / Chrome)
  let deferredPrompt = null;
  const banner = document.getElementById('pwa-banner');
  const installBtn = document.getElementById('pwa-install-btn');
  const dismissBtn = document.getElementById('pwa-dismiss-btn');

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    // Show banner only if not permanently dismissed
    if (!localStorage.getItem('pwa-dismissed') && banner) {
      // Delay so it doesn't interrupt the first interaction
      setTimeout(function () {
        if (banner) banner.style.display = 'flex';
      }, 8000);
    }
  });

  if (installBtn) {
    installBtn.addEventListener('click', function () {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () {
        deferredPrompt = null;
        if (banner) banner.style.display = 'none';
      });
    });
  }

  if (dismissBtn) {
    dismissBtn.addEventListener('click', function () {
      if (banner) banner.style.display = 'none';
      localStorage.setItem('pwa-dismissed', '1');
    });
  }

  // iOS install hint — only show when user taps the install hint button
  const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.navigator.standalone === true;
  const iosHint = document.getElementById('ios-hint');
  const iosClose = document.getElementById('ios-hint-close');
  const iosTrigger = document.getElementById('ios-install-trigger');

  if (iosTrigger) {
    if (isIOS && !isStandalone) {
      iosTrigger.style.display = 'flex';
    }
    iosTrigger.addEventListener('click', function () {
      if (iosHint) iosHint.style.display = 'block';
    });
  }

  if (iosClose) {
    iosClose.addEventListener('click', function () {
      if (iosHint) iosHint.style.display = 'none';
    });
  }

  // Hide banners when app is already installed
  window.addEventListener('appinstalled', function () {
    if (banner) banner.style.display = 'none';
    if (iosHint) iosHint.style.display = 'none';
    if (iosTrigger) iosTrigger.style.display = 'none';
  });
});
