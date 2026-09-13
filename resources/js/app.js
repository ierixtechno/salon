

import Alpine from 'alpinejs';
import QRCode from 'qrcode';

window.Alpine = Alpine;

// Renders every UPI QR canvas on the page (see
// resources/views/components/upi-qr-code.blade.php) — data-upi-uri holds
// the upi://pay deep link built server-side (BuildUpiPaymentUri). A failed
// render (e.g. an unexpected empty URI) is logged, not thrown — a missing
// QR image must never break the rest of the page.
function renderUpiQrCodes() {
    document.querySelectorAll('canvas[data-upi-qr]').forEach((canvas) => {
        const uri = canvas.dataset.upiUri;
        if (!uri) {
            return;
        }
        QRCode.toCanvas(canvas, uri, { width: 160, margin: 1 }, (error) => {
            if (error) {
                console.error('Failed to render UPI QR code', error);
            }
        });
    });
}
document.addEventListener('DOMContentLoaded', renderUpiQrCodes);

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
