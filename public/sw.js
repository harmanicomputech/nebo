/*
 | Nebo Stage service worker.
 |
 | Security rule: pages and data are NEVER cached. Internal screens show live
 | operational data and devices get shared or lost, so HTML navigations always
 | go to the network; if the network is down the offline page is shown.
 | Only static, fingerprinted assets (/build), icons, the brand logo files and
 | the offline page are cached. Bump VERSION whenever icons or logos change.
 */
const VERSION = 'nebo-v3'; // v3: push notifications (D75); icons moved to /app-icons (Apache reserves /icons/)
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URL = '/offline';
const MAX_ASSETS = 80;

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll(['/app-icons/icon-192.png', '/favicon.svg', '/manifest.webmanifest', '/images/brand/nebo-stage-white.svg']);

        // Cache the offline page and the CSS/JS/fonts it needs to render.
        const res = await fetch(OFFLINE_URL, { cache: 'no-store', credentials: 'omit' });
        if (res.ok) {
            const html = await res.clone().text();
            await cache.put(OFFLINE_URL, res);
            const assets = [...html.matchAll(/(?:href|src)="([^"]*\/(?:build|images\/brand)\/[^"]+)"/g)].map((m) => new URL(m[1], self.location.origin).pathname);
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

const isStatic = (url) => url.pathname.startsWith('/build/') || url.pathname.startsWith('/app-icons/') || url.pathname.startsWith('/images/brand/') || url.pathname === '/favicon.svg';

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

/*
 | Pop-up notifications (D75). Each push carries {title, body, url, tag}.
 | If a Nebo Stage page is open and in front, it shows its own pop-up card;
 | otherwise (or on Apple devices, which require it) a system notification.
 */
const isAppleWebKit = () => /iPhone|iPad|iPod/.test(self.navigator.userAgent)
    || (/Safari/.test(self.navigator.userAgent) && !/Chrome|Chromium|Edg|Firefox/.test(self.navigator.userAgent));

self.addEventListener('push', (event) => {
    let item = {};
    try {
        item = event.data ? event.data.json() : {};
    } catch {
        item = { title: 'Nebo Stage', body: event.data ? event.data.text() : '' };
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        windows.forEach((w) => w.postMessage({ type: 'nebo:notification', item }));
        const inFront = windows.some((w) => w.focused && w.visibilityState === 'visible');
        if (inFront && !isAppleWebKit()) return;

        await self.registration.showNotification(item.title || 'Nebo Stage', {
            body: item.body || '',
            icon: '/app-icons/icon-192.png',
            badge: '/app-icons/badge-96.png',
            tag: item.tag || undefined,
            renotify: Boolean(item.tag),
            data: { url: item.url || '/app/notifications' },
            vibrate: [80, 40, 80],
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/app/notifications', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const open = windows.find((w) => new URL(w.url).origin === self.location.origin);
        if (open) {
            await open.focus();
            return open.navigate ? open.navigate(target) : undefined;
        }
        return self.clients.openWindow(target);
    })());
});
