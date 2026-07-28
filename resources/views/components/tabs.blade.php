@props(['items', 'default' => null])

@php $defaultTab = $default ?? array_key_first($items); @endphp

<div x-data="{ tab: '{{ $defaultTab }}' }">
    <div class="border-b border-gray-200 mb-6 overflow-x-auto">
        <nav class="-mb-px flex gap-x-6 whitespace-nowrap" aria-label="Tabs">
            @foreach ($items as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="border-b-2 py-3 px-1 text-sm font-medium transition-colors duration-150">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{ $slot }}
</div>
