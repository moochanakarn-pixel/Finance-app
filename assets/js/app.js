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
    // Don't show if dismissed before
    if (!sessionStorage.getItem('pwa-dismissed') && banner) {
      banner.style.display = 'flex';
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
      sessionStorage.setItem('pwa-dismissed', '1');
    });
  }

  // iOS install hint (Safari on iPhone/iPad, not in standalone mode)
  const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.navigator.standalone === true;
  const iosHint = document.getElementById('ios-hint');
  const iosClose = document.getElementById('ios-hint-close');

  if (isIOS && !isStandalone && iosHint && !sessionStorage.getItem('ios-hint-dismissed')) {
    setTimeout(function () { iosHint.style.display = 'block'; }, 3000);
  }

  if (iosClose) {
    iosClose.addEventListener('click', function () {
      if (iosHint) iosHint.style.display = 'none';
      sessionStorage.setItem('ios-hint-dismissed', '1');
    });
  }

  // Hide banners when app is already installed
  window.addEventListener('appinstalled', function () {
    if (banner) banner.style.display = 'none';
    if (iosHint) iosHint.style.display = 'none';
  });
});
