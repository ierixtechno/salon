// Minimal PWA service worker.
//
// This app is a live, multi-tenant business system (POS, appointments,
// inventory, invoicing) — serving stale cached HTML or data offline could
// show incorrect financial/inventory state (CLAUDE.md §45/§46), so this
// deliberately does NOT do general offline caching. It only caches the
// small set of static, non-hashed shell assets below (icons/manifest),
// and exists mainly to provide the fetch handler that browsers require
// before they'll offer "Add to Home Screen" / install prompts.
const CACHE_NAME = 'app-shell-v1';
const SHELL_ASSETS = [
    '/favicon.ico',
    '/favicon.png',
    '/apple-touch-icon.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

// Web Push: show the notification the server sent (title/body/url), and open
// or focus the app at that URL when it's tapped.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = {}; }

    event.waitUntil(self.registration.showNotification(data.title || 'StyloBiz', {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: data.tag || undefined,
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const client of windows) {
            if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                return client.navigate(target).then((c) => c && c.focus());
            }
        }
        return self.clients.openWindow(target);
    }));
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin === self.location.origin && SHELL_ASSETS.includes(url.pathname)) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
    }

    // Everything else (pages, API calls, hashed build assets) passes
    // through to the network untouched — no offline fallback, on purpose.
});
