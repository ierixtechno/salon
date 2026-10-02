// Phone/desktop push alerts: subscribe this browser to the server's Web Push
// (VAPID) service and tell Laravel about it. Used by the bell dropdown.
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;

const urlBase64ToUint8Array = (base64) => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(padded);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
};

const post = (url, body) => fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
    body: JSON.stringify(body),
});

const supported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

// 'unsupported' | 'blocked' | 'off' | 'on'
async function state() {
    if (!supported()) return 'unsupported';
    if (Notification.permission === 'denied') return 'blocked';
    const registration = await navigator.serviceWorker.ready;
    return (await registration.pushManager.getSubscription()) ? 'on' : 'off';
}

async function enable() {
    const info = await (await fetch('/push/key', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })).json();
    if (!info.enabled) throw new Error('Push is not set up on this server yet.');

    if ((await Notification.requestPermission()) !== 'granted') return 'blocked';

    const registration = await navigator.serviceWorker.ready;
    const subscription = (await registration.pushManager.getSubscription())
        || await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(info.key) });

    const response = await post('/push/subscribe', subscription.toJSON());
    if (!response.ok) {
        await subscription.unsubscribe();
        throw new Error('Could not save this device.');
    }
    return 'on';
}

async function disable() {
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
        await post('/push/unsubscribe', { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
    }
    return 'off';
}

window.stylobizPush = { state, enable, disable };
