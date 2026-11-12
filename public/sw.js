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

// Network only: pages and data are never cached, so signed-in content cannot be read from a stale copy.
// The handler is here so the browser treats the app as installable; a page shown while offline gets a notice.
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => new Response(
            '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>لا يوجد اتصال</title><body style="font-family:sans-serif;text-align:center;padding:40px;background:#0b0f14;color:#e5e7eb"><h2>لا يوجد اتصال بالإنترنت</h2><p>افتح التطبيق مرة ثانية عند توفر الاتصال.</p></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        ))
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/admin';

    event.waitUntil(clients.openWindow(url));
});
