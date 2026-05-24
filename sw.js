const CACHE = 'finance-v1';
const STATIC = [
  './',
  './assets/css/style.css',
  './assets/js/app.js',
  './finance-icon-dark.svg',
  './assets/icon-192.png',
  './assets/icon-512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Network-first for PHP pages, cache-first for static assets
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  // Skip non-GET and cross-origin non-CDN
  if (e.request.method !== 'GET') return;

  // PHP pages: network-first, fallback to offline page
  if (url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
    e.respondWith(
      fetch(e.request).catch(() =>
        caches.match('./') || new Response('<h1>ออฟไลน์</h1><p>กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</p>', {
          headers: { 'Content-Type': 'text/html; charset=utf-8' }
        })
      )
    );
    return;
  }

  // Static assets: cache-first
  e.respondWith(
    caches.match(e.request).then(cached => cached || fetch(e.request).then(resp => {
      if (resp.ok) {
        const clone = resp.clone();
        caches.open(CACHE).then(c => c.put(e.request, clone));
      }
      return resp;
    }))
  );
});
