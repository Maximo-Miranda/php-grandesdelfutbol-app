/// <reference lib="webworker" />

import { ExpirationPlugin } from 'workbox-expiration';
import { precacheAndRoute } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { CacheFirst, NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies';

declare const self: ServiceWorkerGlobalScope;

// Allow the app to trigger skipWaiting via postMessage
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// Precache assets (Workbox injects the manifest automatically)
precacheAndRoute(self.__WB_MANIFEST);

// Navigation requests always go to the network (Inertia SPA)
registerRoute(new NavigationRoute(new NetworkFirst({ cacheName: 'pages' })));

// Runtime caching: images
registerRoute(
    /\.(?:png|jpg|jpeg|svg|gif|webp)$/i,
    new StaleWhileRevalidate({
        cacheName: 'images',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100,
                maxAgeSeconds: 60 * 60 * 24 * 30,
            }),
        ],
    }),
);

// Runtime caching: fonts
registerRoute(
    /\.(?:woff2?|ttf|eot)$/i,
    new CacheFirst({
        cacheName: 'fonts',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 20,
                maxAgeSeconds: 60 * 60 * 24 * 365,
            }),
        ],
    }),
);

// Push notification handler
self.addEventListener('push', (event) => {
    let payload: Record<string, unknown> = {};
    try {
        payload = event.data?.json() ?? {};
    } catch {
        // Fallback for plain text (e.g. DevTools test push)
        payload = { body: event.data?.text() ?? '' };
    }
    event.waitUntil(
        self.registration.showNotification((payload.title as string) ?? 'Grandes del Futbol', {
            body: payload.body as string,
            icon: (payload.icon as string) ?? '/pwa-192x192.png',
            badge: (payload.badge as string) ?? '/badge-96x96.png',
            data: (payload.data as object) ?? {},
            tag: payload.tag as string,
        }),
    );
});

// Click handler — open the notification URL
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url;
    if (url) {
        event.waitUntil(self.clients.openWindow(url));
    }
});
