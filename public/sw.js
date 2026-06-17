/* Service Worker — Torneos Ping Pong PWA
 * Requisito para que el navegador ofrezca "Instalar / Agregar a pantalla de inicio".
 * Estrategia: cache-first para assets estáticos, network-first para páginas.
 */
const CACHE = 'pingpong-v1';
const ASSETS = [
    '/assets/css/app.css',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(ASSETS))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // No interferir con POST (login, formularios, carga masiva, cambios de rol).
    if (req.method !== 'GET') {
        return;
    }

    const url = new URL(req.url);
    // Solo manejar peticiones del mismo origen.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Assets estáticos: cache-first (rápidos y cacheables).
    if (/\.(css|js|png|jpg|jpeg|svg|ico|woff2?)$/.test(url.pathname)) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((cache) => cache.put(req, copy));
                return res;
            }))
        );
        return;
    }

    // Navegación / HTML: network-first (datos siempre frescos), con caché de respaldo offline.
    event.respondWith(
        fetch(req)
            .then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((cache) => cache.put(req, copy));
                return res;
            })
            .catch(() => caches.match(req))
    );
});
