// Setlo service worker: makes the app installable and keeps the shell usable on a flaky connection.
// Money data (api/) is never cached — balances must always come from the server.
const VERSION = 'setlo-v3';
const SHELL = [
  'offline.html',
  'assets/css/app.css',
  'assets/js/api.js',
  'assets/js/ui.js',
  'assets/js/summary.js',
  'assets/js/validate.js',
  'assets/icons/icon-192.png',
  'assets/setlo_logo.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // Live data and uploads: network only.
  if (url.pathname.includes('/api/') || url.pathname.includes('/uploads/')) return;

  // Pages: always try the network (they carry the session + CSRF token); offline page as a fallback.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }

  // Our static files: serve cached, refresh in the background.
  if (url.origin === self.location.origin && url.pathname.includes('/assets/')) {
    event.respondWith(
      caches.open(VERSION).then(async (cache) => {
        // Key by path only, so each ?v= deploy replaces the old copy instead of piling up.
        const key = url.origin + url.pathname;
        const cached = await cache.match(key);
        const fresh = fetch(req).then((res) => { if (res.ok) cache.put(key, res.clone()); return res; }).catch(() => cached);
        return cached || fresh;
      })
    );
    return;
  }

  // Vue, QR library and fonts from the CDNs: cache after first use.
  if (/cdn\.jsdelivr\.net|fonts\.(googleapis|gstatic)\.com/.test(url.host)) {
    event.respondWith(
      caches.open(VERSION).then(async (cache) => {
        const cached = await cache.match(req);
        if (cached) return cached;
        const res = await fetch(req);
        if (res.ok) cache.put(req, res.clone()); // CORS responses only, so integrity checks keep working
        return res;
      })
    );
  }
});
