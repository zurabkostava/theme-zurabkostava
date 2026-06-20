// ==== Wordevo Service Worker ====
const SW_VERSION = 14;
const PUSH_URL = 'https://wdgvxerfxwtmpqztwgtj.supabase.co/functions/v1/get-push-notification';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
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
                            await new Promise(r => setTimeout(r, 2000));
                        }
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

        return self.registration.showNotification(title, {
            body,
            tag,
            icon: './icons/icon-192.png',
            badge: './icons/icon-192.png',
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
        self.registration.showNotification(d.title || 'Wordevo', {
            body: d.body || '',
            icon: './icons/icon-192.png',
            badge: './icons/icon-192.png',
            tag: d.tag || 'wordevo-reminder',
            renotify: true,
            vibrate: [200, 100, 200],
            requireInteraction: true,
        });
    }
});

// ==== Click: open/focus app ====
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clients => clients.length > 0 ? clients[0].focus() : self.clients.openWindow('./'))
    );
});

