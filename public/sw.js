/*
 | Nebo Stage service worker.
 |
 | Security rule: pages and data are NEVER cached. Internal screens show live
 | operational data and devices get shared or lost, so HTML navigations always
 | go to the network; if the network is down the offline page is shown.
 | Only static, fingerprinted assets (/build), icons and the offline page are cached.
 */
const VERSION = 'nebo-v1';
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URL = '/offline';
const MAX_ASSETS = 80;

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll(['/icons/icon-192.png', '/favicon.svg', '/manifest.webmanifest']);

        // Cache the offline page and the CSS/JS/fonts it needs to render.
        const res = await fetch(OFFLINE_URL, { cache: 'no-store', credentials: 'omit' });
        if (res.ok) {
            const html = await res.clone().text();
            await cache.put(OFFLINE_URL, res);
            const assets = [...html.matchAll(/(?:href|src)="([^"]*\/build\/[^"]+)"/g)].map((m) => new URL(m[1], self.location.origin).pathname);
            await Promise.all(assets.map((a) => cache.add(a).catch(() => {})));
        }
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => k.startsWith('nebo-') && !k.startsWith(VERSION)).map((k) => caches.delete(k)));
        await self.clients.claim();
    })());
});

const isStatic = (url) => url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/favicon.svg';

async function trim(cache) {
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - MAX_ASSETS; i++) {
        if (!keys[i].url.endsWith(OFFLINE_URL)) await cache.delete(keys[i]);
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => (await caches.match(OFFLINE_URL)) || Response.error()));
        return;
    }

    if (isStatic(url)) {
        // Fingerprinted assets never change: cache-first.
        event.respondWith((async () => {
            const cached = await caches.match(request);
            if (cached) return cached;
            const res = await fetch(request);
            if (res.ok && res.type === 'basic') {
                const cache = await caches.open(STATIC_CACHE);
                await cache.put(request, res.clone());
                trim(cache);
            }
            return res;
        })());
    }
    // Everything else (pages, JSON, uploads): straight to the network, never cached.
});
