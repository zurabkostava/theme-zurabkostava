const CACHE_NAME = 'neural-reader-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    // Do not cache or intercept dynamic TTS audio streams or external API calls
    const url = new URL(event.request.url);
    if (url.pathname.includes('google-tts.php') || url.hostname.includes('puter.com') || url.pathname.includes('/neural/v1/')) {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
