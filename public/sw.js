/* The Red Star service worker — offline "Save edition".
 * - /build/* (hashed assets): cache-first, cached as they are fetched.
 * - /files/* (signed page images): cache-first; offline falls back to the same key ignoring the signature.
 * - Navigations: network-first; offline falls back to a saved edition page (ignoring ?page=N).
 * Saving/removing an edition is done from the reader with the Cache API (cache "redstar-offline"). */
const ASSETS = 'redstar-assets-v1';
const OFFLINE = 'redstar-offline';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) caches.open(ASSETS).then((c) => c.put(req, res.clone()));
            return res;
        })));
        return;
    }
    if (url.pathname.startsWith('/files/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).catch(() =>
            caches.open(OFFLINE).then((c) => c.match(url.origin + url.pathname, { ignoreSearch: true }))
                .then((r) => r || Response.error()))));
        return;
    }
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() =>
            caches.open(OFFLINE).then((c) => c.match(url.origin + url.pathname, { ignoreSearch: true }))
                .then((r) => r || new Response('<h1>You are offline</h1><p>Open an edition you saved for offline reading.</p>',
                    { headers: { 'Content-Type': 'text/html' } }))));
    }
});
