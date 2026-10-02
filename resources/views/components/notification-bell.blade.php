@php
    $bellInitial = \App\Http\Controllers\Core\NotificationController::bellPayload(auth('web')->user());
@endphp

{{-- Top-bar bell: unread badge + latest notifications. Refreshes every 60s
     while the tab is visible (plain polling — no websockets, shared-hosting
     friendly) and whenever it's opened. --}}
<div class="relative" x-data="{
        open: false,
        unread: {{ $bellInitial['unread'] }},
        items: @js($bellInitial['items']),
        async refresh() {
            try {
                const r = await fetch('{{ route('notifications.summary') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) return;
                const d = await r.json();
                this.unread = d.unread; this.items = d.items;
            } catch (e) {}
        },
        async readAll() {
            try {
                const r = await fetch('{{ route('notifications.read-all') }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, credentials: 'same-origin' });
                if (r.ok) { const d = await r.json(); this.unread = d.unread; this.items = d.items; }
            } catch (e) {}
        },
        init() { setInterval(() => { if (!document.hidden) this.refresh(); }, 60000); },
    }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open; if (open) refresh()" class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition" aria-label="Notifications">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread" class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] rounded-full bg-pink-600 px-1 text-center text-[10px] font-semibold leading-[1.1rem] text-white"></span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 z-50 mt-2 w-80 max-w-[90vw] rounded-lg bg-white shadow-lg ring-1 ring-black/5">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
            <p class="text-sm font-semibold text-gray-900">Notifications</p>
            <button type="button" x-show="unread > 0" @click="readAll()" class="text-xs text-indigo-600 hover:text-indigo-800">Mark all read</button>
        </div>
        <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
            <template x-for="n in items" :key="n.id">
                <li class="px-4 py-2.5" :class="n.unread ? 'bg-pink-50/60' : ''">
                    <p class="text-sm text-gray-900" :class="n.unread ? 'font-medium' : ''" x-text="n.title"></p>
                    <p class="text-xs text-gray-500" x-show="n.body" x-text="n.body"></p>
                    <p class="mt-0.5 text-[11px] text-gray-400" x-text="n.time"></p>
                </li>
            </template>
            <li x-show="items.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">No notifications yet.</li>
        </ul>
        @if (\App\Domain\Core\Support\WebPushSender::configured())
            <div class="border-t border-gray-100 px-4 py-2.5 text-xs" x-data="{
                    push: 'checking', busy: false, error: '',
                    async init() { this.push = window.stylobizPush ? await window.stylobizPush.state() : 'unsupported'; },
                    async toggle() {
                        this.busy = true; this.error = '';
                        try { this.push = this.push === 'on' ? await window.stylobizPush.disable() : await window.stylobizPush.enable(); }
                        catch (e) { this.error = e.message || 'Something went wrong.'; }
                        this.busy = false;
                    },
                }">
                <template x-if="push === 'off' || push === 'on'">
                    <button type="button" @click="toggle()" :disabled="busy" class="font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                        x-text="push === 'on' ? 'Turn off alerts on this device' : 'Get alerts on this device'"></button>
                </template>
                <p x-show="push === 'blocked'" class="text-gray-500">Alerts are blocked for this site. Allow notifications in your browser settings to turn them on.</p>
                <p x-show="push === 'unsupported'" class="text-gray-500">To get alerts on iPhone, install the app first (Share &rarr; Add to Home Screen) and open it from there.</p>
                <p x-show="error" x-text="error" class="mt-1 text-red-600"></p>
            </div>
        @endif
        <a href="{{ route('notifications.my') }}" class="block border-t border-gray-100 px-4 py-2.5 text-center text-sm font-medium text-indigo-600 hover:bg-gray-50">View all</a>
    </div>
</div>
