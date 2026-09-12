

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Captured as early as possible — Chrome/Edge only fire this once, and
// only if a listener is already attached, so the sidebar's "Download
// Mobile App" button (resources/views/layouts/navigation.blade.php) can
// trigger the real install prompt whenever the browser has one ready.
window.deferredInstallPrompt = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.deferredInstallPrompt = event;
});

Alpine.start();

// PWA install support (see public/sw.js and public/manifest.webmanifest) —
// registered from every page since app.js is loaded by every layout.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Installability is a progressive enhancement — a failed
            // registration (e.g. unsupported browser, dev environment
            // quirk) must never block the app itself from working.
        });
    });
}
