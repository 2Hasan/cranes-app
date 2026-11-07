@auth
    @if (config('webpush.vapid.public_key'))
        <div id="push-setup" hidden style="position: fixed; bottom: 16px; inset-inline-start: 16px; z-index: 50;">
            <button id="push-enable" type="button"
                style="background: #16324a; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.25);">
                تفعيل إشعارات الجوال
            </button>
        </div>

        <script>
            (function () {
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                    return;
                }

                const publicKey = @js(config('webpush.vapid.public_key'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const setup = document.getElementById('push-setup');
                const button = document.getElementById('push-enable');

                function toApplicationKey(base64Url) {
                    const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
                    const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
                    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
                }

                async function subscribe() {
                    const registration = await navigator.serviceWorker.register('/sw.js');
                    await navigator.serviceWorker.ready;

                    let subscription = await registration.pushManager.getSubscription();

                    if (!subscription) {
                        subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: toApplicationKey(publicKey),
                        });
                    }

                    const response = await fetch('/push/subscribe', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(subscription.toJSON()),
                    });

                    return response.ok;
                }

                if (Notification.permission === 'granted') {
                    // Keeps the subscription fresh on every visit; nothing is shown to the user.
                    subscribe().catch(() => {});
                    return;
                }

                if (Notification.permission === 'default') {
                    setup.hidden = false;
                }

                button.addEventListener('click', async () => {
                    const permission = await Notification.requestPermission();

                    if (permission === 'granted' && await subscribe()) {
                        setup.hidden = true;
                    }
                });
            })();
        </script>
    @endif
@endauth
