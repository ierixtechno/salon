@props(['title', 'active' => false])

<div x-data="{ open: {{ $active ? 'true' : 'false' }} }" class="pt-2">
    <button type="button" @click="open = !open"
        class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors duration-150">
        <span>{{ $title }}</span>
        <svg class="h-3.5 w-3.5 shrink-0 transition-transform duration-150" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>
    <div x-show="open" class="space-y-1">
        {{ $slot }}
    </div>
</div>
