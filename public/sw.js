/**
 * Walkie-Talkie Service Worker
 * Provides offline caching for application shell and static assets.
 * Dynamic signaling (/signal) and WebSocket traffic are strictly NEVER cached.
 */

const CACHE_NAME = 'talkie-shell-v1';
const STATIC_ASSETS = [
    './',
    'manifest.webmanifest',
    'assets/css/app.css',
    'assets/js/sound-fx.js',
    'assets/js/audio-level.js',
    'assets/js/signaling.js',
    'assets/js/webrtc.js',
    'assets/js/ptt.js',
    'assets/js/pwa.js',
    'assets/js/app.js',
    'assets/icons/icon.svg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[ServiceWorker] Pre-caching application shell');
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('[ServiceWorker] Pre-cache partial fail:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(
                keyList.map((key) => {
                    if (key !== CACHE_NAME) {
                        console.log('[ServiceWorker] Removing obsolete cache:', key);
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // 1. Never cache POST requests, signaling API, or dynamic WebRTC payloads
    if (request.method !== 'GET' || url.pathname.includes('/signal') || url.pathname.includes('/join') || url.pathname.includes('/leave')) {
        return;
    }

    // 2. Cache-first strategy for static assets (CSS, JS, Fonts, Icons)
    if (
        url.pathname.includes('/assets/') ||
        url.hostname.includes('cdn.jsdelivr.net') ||
        url.hostname.includes('cdnjs.cloudflare.com') ||
        url.hostname.includes('fonts.googleapis.com') ||
        url.hostname.includes('fonts.gstatic.com')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // 3. Network-first strategy for HTML pages with offline fallback
    event.respondWith(
        fetch(request)
            .then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                return caches.match(request).then((cached) => cached || caches.match('./'));
            })
    );
});
