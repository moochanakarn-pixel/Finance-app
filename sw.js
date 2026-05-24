const CACHE = 'finance-v2';
const STATIC = [
  './assets/css/style.css',
  './assets/js/app.js',
  './finance-icon-dark.svg',
  './assets/icon-192.png',
  './assets/icon-512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.min.js'
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE)
      .then(c => c.addAll(STATIC))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET') return;

  // PHP pages: network-first (always fresh data)
  if (url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
    e.respondWith(
      fetch(e.request).catch(() =>
        caches.match('./').then(r => r || new Response(
          '<meta charset="utf-8"><h2 style="font-family:sans-serif;text-align:center;margin-top:3rem">ออฟไลน์</h2><p style="text-align:center">กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</p>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        ))
      )
    );
    return;
  }

  // CDN + static assets: cache-first (fast on repeat visits)
  e.respondWith(
    caches.match(e.request).then(cached => {
      if (cached) return cached;
      return fetch(e.request).then(resp => {
        if (resp.ok) {
          const clone = resp.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return resp;
      });
    })
  );
});
