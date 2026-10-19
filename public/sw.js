/*
 * Service worker of Ruta Laravel: makes the app installable and lets the lessons that were
 * opened be read without a connection.
 *
 * Plain JavaScript with no build step, served from the root so its scope is the whole app.
 * Change VERSION when this file changes in a way that old caches must not survive.
 *
 * What it keeps, and why:
 *  - The offline page and the icon: shown when a page cannot be reached.
 *  - Built files in /build/assets/: their names carry a hash, so they never change.
 *  - The pages under /learn (the index, each module and each lesson), for offline reading.
 *    They are fetched from the network first, and the saved copy is only used when there
 *    is no network, so a reader online always sees the current page.
 *
 * What it never keeps: anything else (the dashboard, flashcards, forms, API calls). Those
 * depend on who is signed in and on today's progress, and a stale copy would mislead.
 * The page deletes the saved lessons when the user signs out or a different user signs
 * in (resources/js/lib/pwa.ts).
 */

const VERSION = 'v1';

const STATIC_CACHE = `ruta-static-${VERSION}`;
const ASSETS_CACHE = `ruta-assets-${VERSION}`;
const PAGES_CACHE = `ruta-pages-${VERSION}`;

const OFFLINE_URL = '/offline.html';

// The most lessons and pages kept; the oldest go first.
const MAX_PAGES = 80;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const current = new Set([STATIC_CACHE, ASSETS_CACHE, PAGES_CACHE]);

            for (const key of await caches.keys()) {
                if (key.startsWith('ruta-') && !current.has(key)) {
                    await caches.delete(key);
                }
            }

            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request, ASSETS_CACHE));

        return;
    }

    if (url.pathname === '/learn' || url.pathname.startsWith('/learn/')) {
        event.respondWith(networkFirst(request, PAGES_CACHE));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => offlinePage()));
    }
});

async function offlinePage() {
    return (await caches.match(OFFLINE_URL)) ?? Response.error();
}

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const saved = await cache.match(request);

    if (saved) {
        return saved;
    }

    const response = await fetch(request);

    if (response.ok) {
        await cache.put(request, response.clone());
    }

    return response;
}

async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);

        // A redirect means the user was sent to the login page: that is not the lesson.
        if (response.ok && !response.redirected) {
            await cache.put(request, response.clone());
            await trim(cache);
        }

        return response;
    } catch (error) {
        const saved = await cache.match(request);

        if (saved) {
            return saved;
        }

        // A full page load gets the offline page; an in-app visit (which asks for JSON)
        // gets the network error, which the app turns into a message.
        if (request.mode === 'navigate') {
            return offlinePage();
        }

        throw error;
    }
}

async function trim(cache) {
    const keys = await cache.keys();

    for (const request of keys.slice(0, Math.max(0, keys.length - MAX_PAGES))) {
        await cache.delete(request);
    }
}
