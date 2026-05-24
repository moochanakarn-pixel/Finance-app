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
