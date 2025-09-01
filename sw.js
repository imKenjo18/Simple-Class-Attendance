/* Simple-Class-Attendance Service Worker
 * Strategy:
 * - Stale-While-Revalidate for JS/CSS (fast, then updates in background)
 * - Cache-First for fonts/images
 * - Network-Only for API calls and POSTs
 * - Auto-skip-waiting and notify clients to reload on activate
 */

const CACHE_VERSION = 'sca-v1.0.0';
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;
const ASSETS_CACHE = `${CACHE_VERSION}-assets`;

// Only pre-cache static assets. Avoid HTML/PHP to prevent caching private pages.
const PRECACHE_URLS = [
  // CSS
  'assets/css/style.css',
  'assets/css/google-fonts.css',
  'assets/css/auth.css',
  // JS
  'assets/js/app.js',
  'assets/js/html5-qrcode.min.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(ASSETS_CACHE)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      // Delete old caches
      const keys = await caches.keys();
      await Promise.all(
        keys.map((key) => {
          if (!key.startsWith(CACHE_VERSION)) {
            return caches.delete(key);
          }
        })
      );
      await self.clients.claim();
      // Notify clients to reload once activated
      const clientsArr = await self.clients.matchAll({ type: 'window' });
      for (const client of clientsArr) {
        client.postMessage({ type: 'SW_ACTIVATED' });
      }
    })()
  );
});

// Helpers
async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const fetchPromise = fetch(request)
    .then((response) => {
      if (response && response.ok) {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => cached);
  return cached || fetchPromise;
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response && response.ok) {
    cache.put(request, response.clone());
  }
  return response;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Bypass non-GET requests
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  const isSameOrigin = url.origin === self.location.origin;

  // Never cache API calls
  if (isSameOrigin && url.pathname.includes('/api/')) {
    return; // default network behavior
  }

  // Runtime strategies
  if (request.destination === 'style' || request.destination === 'script' || request.destination === 'worker') {
    event.respondWith(staleWhileRevalidate(request, RUNTIME_CACHE));
    return;
  }

  if (request.destination === 'font' || request.destination === 'image') {
    event.respondWith(cacheFirst(request, RUNTIME_CACHE));
    return;
  }

  // For navigations/HTML: network first with cache fallback
  if (request.mode === 'navigate' || request.destination === 'document') {
    event.respondWith(
      fetch(request).catch(() => caches.match(request))
    );
    return;
  }

  // Default: try SWR
  event.respondWith(staleWhileRevalidate(request, RUNTIME_CACHE));
});

// Allow the page to trigger skipWaiting explicitly
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
