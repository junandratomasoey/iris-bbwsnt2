// Service worker IRIS: aset statis & GeoJSON di-cache; halaman selalu dari jaringan (data harus terkini).
const CACHE = 'iris-v1';
self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
    self.clients.claim();
});
self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/geojson/') || url.pathname === '/ikon.svg') {
        e.respondWith(caches.open(CACHE).then(async (c) => {
            const hit = await c.match(e.request);
            const net = fetch(e.request).then((r) => { if (r.ok) c.put(e.request, r.clone()); return r; }).catch(() => hit);
            return hit || net;
        }));
    }
});
