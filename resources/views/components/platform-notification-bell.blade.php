@php
    $bellInitial = \App\Http\Controllers\Platform\PlatformNotificationController::bellPayload(auth('platform')->id());
@endphp

{{-- Super Admin bell: unread badge + latest alerts (new quotations, payments,
     renewals, requests). Plain 60s polling while the tab is visible. --}}
<div class="relative" x-data="{
        open: false,
        unread: {{ $bellInitial['unread'] }},
        items: @js($bellInitial['items']),
        async refresh() {
            try {
                const r = await fetch('{{ route('platform.notifications.summary') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) return;
                const d = await r.json();
                this.unread = d.unread; this.items = d.items;
            } catch (e) {}
        },
        async readAll() {
            try {
                const r = await fetch('{{ route('platform.notifications.read-all') }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, credentials: 'same-origin' });
                if (r.ok) { const d = await r.json(); this.unread = d.unread; this.items = d.items; }
            } catch (e) {}
        },
        init() { setInterval(() => { if (!document.hidden) this.refresh(); }, 60000); },
    }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open; if (open) refresh()" class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition" aria-label="Notifications">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread" class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-semibold leading-[1.1rem] text-white"></span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 z-50 mt-2 w-96 max-w-[90vw] rounded-lg bg-white shadow-lg ring-1 ring-black/5">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
            <p class="text-sm font-semibold text-gray-900">Notifications</p>
            <button type="button" x-show="unread > 0" @click="readAll()" class="text-xs text-indigo-600 hover:text-indigo-800">Mark all read</button>
        </div>
        <ul class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
            <template x-for="n in items" :key="n.id">
                <li>
                    <a :href="n.open" class="block px-4 py-2.5 hover:bg-gray-50" :class="n.unread ? 'bg-indigo-50/60' : ''">
                        <p class="text-sm text-gray-900" :class="n.unread ? 'font-medium' : ''" x-text="n.title"></p>
                        <p class="text-xs text-gray-500" x-show="n.body" x-text="n.body"></p>
                        <p class="mt-0.5 text-[11px] text-gray-400" x-text="n.time"></p>
                    </a>
                </li>
            </template>
            <li x-show="items.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">No notifications yet.</li>
        </ul>
        <a href="{{ route('platform.notifications.index') }}" class="block border-t border-gray-100 px-4 py-2.5 text-center text-sm font-medium text-indigo-600 hover:bg-gray-50">View all</a>
    </div>
</div>
