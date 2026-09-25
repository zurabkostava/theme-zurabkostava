// ==== Wordevo Service Worker ====
const SW_VERSION = 19;
const workerUrl = new URL(self.location.href);
const requestedApp = new URL(workerUrl.searchParams.get('app') || '/', workerUrl.origin);
const APP_URL = requestedApp.origin === workerUrl.origin ? requestedApp.href : workerUrl.origin + '/';
const ICON_URL = new URL('./icons/notification-192.png', workerUrl).href;
const BADGE_URL = new URL('./icons/notification-96.png', workerUrl).href;
const PUSH_URL = 'https://wdgvxerfxwtmpqztwgtj.supabase.co/functions/v1/get-push-notification';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

// ==== Push: fetch content from queue, show notification ====
self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let title = 'Wordevo';
        let body = 'დროა გადაიმეოროთ სიტყვები!';
        let tag = 'wordevo-push';

        if (event.data) {
            try {
                const p = event.data.json();
                if (p.title) title = p.title;
                if (p.body) body = p.body;
                if (p.tag) tag = p.tag;
            } catch (e) {}
        }

        if (title === 'Wordevo') {
            try {
                const sub = await self.registration.pushManager.getSubscription();
                if (sub) {
                    const endpointUrl = `${PUSH_URL}?endpoint=${encodeURIComponent(sub.endpoint)}`;
                    let res = null;
                    
                    // 3 retries to allow radio to wake up
                    for (let i = 0; i < 3; i++) {
                        try {
                            res = await fetch(endpointUrl);
                            if (res.ok) break;
                        } catch (err) {
                            if (i === 2) throw err;
                        }
                        // HTTP errors need backoff too, not just network exceptions.
                        if (i < 2) await new Promise(r => setTimeout(r, 2000 * (2 ** i)));
                    }

                    if (res && res.ok) {
                        const d = await res.json();
                        if (d.title) title = d.title;
                        if (d.body && d.body.trim() !== '') body = d.body;
                        if (d.schedule_id) tag = `wordevo-${d.schedule_id}`;
                    }
                }
            } catch (e) {
                console.error('[SW] push fetch error:', e);
            }
        }

        return self.registration.showNotification(title || 'WordEvo', {
            body,
            tag,
            icon: ICON_URL,
            badge: BADGE_URL,
            renotify: true,
            vibrate: [200, 100, 200],
            requireInteraction: true,
        });
    })());
});

// ==== Message from page: show notification directly ====
self.addEventListener('message', event => {
    const d = event.data;
    if (d?.type === 'SHOW_NOTIFICATION') {
        event.waitUntil(self.registration.showNotification(d.title || 'WordEvo', {
            body: d.body || '',
            icon: ICON_URL,
            badge: BADGE_URL,
            tag: d.tag || 'wordevo-reminder',
            renotify: true,
            vibrate: [200, 100, 200],
            requireInteraction: true,
        }));
    }
});

// ==== Click: open/focus app ====
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clients => {
                const target = new URL(APP_URL);
                const existing = clients.find(client => {
                    const url = new URL(client.url);
                    return url.origin === target.origin && url.pathname === target.pathname &&
                        url.searchParams.get('page_id') === target.searchParams.get('page_id');
                });
                return existing ? existing.focus() : self.clients.openWindow(APP_URL);
            })
    );
});


