// Service worker: shows push notifications from the server and opens the app when they are tapped.
self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { title: 'متابعة الرافعات', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'متابعة الرافعات';
    const url = (payload.data && payload.data.url) || '/admin';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            data: { url },
            dir: 'rtl',
            lang: 'ar',
            tag: payload.tag || undefined,
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/admin';

    event.waitUntil(clients.openWindow(url));
});
