var CACHE_VERSION = 'bm-erp-v2';
var STATIC_ASSETS = [
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-512x512.png',
    '/offline.html',
];

// Install: cache static assets + offline fallback
self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_VERSION).then(function (cache) {
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

// Activate: delete old caches
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys
                    .filter(function (k) { return k !== CACHE_VERSION; })
                    .map(function (k) { return caches.delete(k); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

// Fetch: network-first for all app requests, offline.html fallback
self.addEventListener('fetch', function (event) {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    var url = new URL(event.request.url);

    // Cache-first for static icons/images
    if (url.pathname.startsWith('/images/icons/')) {
        event.respondWith(
            caches.match(event.request).then(function (cached) {
                return cached || fetch(event.request).then(function (response) {
                    var clone = response.clone();
                    caches.open(CACHE_VERSION).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                    return response;
                });
            })
        );
        return;
    }

    // Network-first for all other requests (app pages, API, etc.)
    // Retry 1x setelah 700ms sebelum fallback ke offline.html — mitigasi
    // radio contention WiFi+Bluetooth sesaat setelah operasi print BT.
    event.respondWith(
        fetch(event.request).catch(function () {
            return new Promise(function (resolve) {
                setTimeout(resolve, 700);
            }).then(function () {
                return fetch(event.request);
            });
        }).catch(function () {
            return caches.match('/offline.html');
        })
    );
});
