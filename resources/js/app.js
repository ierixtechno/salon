

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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
