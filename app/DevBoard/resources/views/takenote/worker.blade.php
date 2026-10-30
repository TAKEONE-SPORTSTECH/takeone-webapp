{{--
    TakeNote's service worker. Its scope is /me/takenote — it controls the app
    page and nothing else on the site.

    Today it does two small things: it keeps the app's own shell and assets
    (icons, fonts, the icon font) so the app opens instantly and still opens
    with no signal, and it never caches anyone's notes — every request for
    data goes straight to the network. Offline writing (a queue of notes made
    with no signal) is the next step and will live here too.
--}}
'use strict';
const VERSION = 'takenote-1';
const SHELL = @json(route('takenote.app', [], false));
const KEEP = [
    '/images/takenote/icon-192.png',
    '/vendor/bootstrap-icons/bootstrap-icons.min.css',
    '/vendor/instrument-serif/instrument-serif-latin-400-normal.woff2',
    '/vendor/instrument-serif/instrument-serif-latin-400-italic.woff2',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((c) => c.addAll(KEEP.filter(Boolean)).catch(() => {})).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('takenote-') && k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // The app page: the network first (so a sign-out or an update is seen at
    // once), the last copy when there is no network.
    if (req.mode === 'navigate' && url.origin === self.location.origin && url.pathname === SHELL) {
        event.respondWith(
            fetch(req).then((res) => {
                if (res.ok && !res.redirected) {
                    const copy = res.clone();
                    caches.open(VERSION).then((c) => c.put(SHELL, copy));
                }
                return res;
            }).catch(() => caches.match(SHELL).then((hit) => hit || new Response(
                '<!doctype html><meta name=viewport content="width=device-width"><body style="background:#160f24;color:#fff;font:16px system-ui;display:grid;place-items:center;height:100vh;margin:0"><p>TakeNote needs a connection the first time it opens.</p>',
                { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            )))
        );
        return;
    }

    // Our own static files (icons, fonts, the icon font): kept once fetched.
    const ours = url.origin === self.location.origin && (url.pathname.startsWith('/vendor/') || url.pathname.startsWith('/images/takenote/'));
    const fonts = url.origin === 'https://fonts.googleapis.com' || url.origin === 'https://fonts.gstatic.com';
    if (ours || fonts) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok || res.type === 'opaque') {
                const copy = res.clone();
                caches.open(VERSION).then((c) => c.put(req, copy));
            }
            return res;
        })));
    }
    // Everything else — every note, every picture — goes to the network untouched.
});
