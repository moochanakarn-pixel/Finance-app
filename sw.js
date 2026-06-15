const CACHE = 'finance-v3';
const STATIC = [
  './assets/css/style.css',
  './assets/js/app.js',
  './finance-icon-dark.svg',
  './assets/icon-192.png',
  './assets/icon-512.png',
  './manifest.json',
  './apple-touch-icon.png',
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

  // PHP pages: network-first, cache on success, serve cached on failure
  if (url.pathname.endsWith('.php') || url.pathname === '/' || url.pathname.endsWith('/')) {
    e.respondWith(
      fetch(e.request)
        .then(resp => {
          if (resp.ok) {
            const clone = resp.clone();
            caches.open(CACHE).then(c => c.put(e.request, clone));
          }
          return resp;
        })
        .catch(() =>
          caches.match(e.request).then(cached =>
            cached || new Response(
              '<meta charset="utf-8"><html><body style="font-family:sans-serif;text-align:center;padding:3rem"><h2>📶 ออฟไลน์</h2><p style="color:#64748b">ข้อมูลล่าสุดที่บันทึกไว้ไม่มีสำหรับหน้านี้<br>กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</p><a href="/" style="color:#6366f1;font-weight:700">↩ กลับหน้าหลัก</a></body></html>',
              { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            )
          )
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
