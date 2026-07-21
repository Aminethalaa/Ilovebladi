/* Baladiyati service worker: offline shell + Web Push. */
const CACHE = 'baladiyati-v1';
const CORE = [
  'offline.html',
  'assets/css/style.css',
  'assets/js/app.js',
  'assets/img/icon-192.png',
  'manifest.json',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(CORE)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Pages: network first, offline fallback.
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() =>
        caches.match(req).then((hit) => hit || caches.match('offline.html'))
      )
    );
    return;
  }
  // Static assets + photos: cache first, then network (and store a copy).
  if (/\/(assets|uploads)\//.test(url.pathname) || /\.(css|js|png|jpg|jpeg|webp|json)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then((hit) =>
        hit ||
        fetch(req).then((res) => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
          }
          return res;
        })
      )
    );
  }
});

self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { body: e.data && e.data.text() }; }
  const title = d.title || 'بلديتي — Baladiyati';
  const opts = {
    body: d.body || '',
    icon: d.icon || 'assets/img/icon-192.png',
    badge: d.icon || 'assets/img/icon-192.png',
    dir: 'rtl',
    data: { url: d.url || './' },
  };
  if (d.image) opts.image = d.image;
  e.waitUntil(self.registration.showNotification(title, opts));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = (e.notification.data && e.notification.data.url) || './';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) {
        if ('focus' in c) { c.navigate(target); return c.focus(); }
      }
      return clients.openWindow(target);
    })
  );
});
