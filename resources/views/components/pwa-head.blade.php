{{-- Lets signed-in users install the admin as an app (PWA). --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#16324a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="الرافعات">
<link rel="apple-touch-icon" href="/icons/icon-192.png">

@auth
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {});
            });
        }
    </script>
@endauth
