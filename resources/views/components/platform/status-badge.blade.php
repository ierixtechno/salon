@props(['status'])

@php
    $styles = [
        'trial' => 'bg-amber-500/10 text-amber-600',
        'active' => 'bg-green-500/10 text-green-600',
        'suspended' => 'bg-orange-500/10 text-orange-600',
        'cancelled' => 'bg-gray-500/10 text-gray-500',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize '.($styles[$status] ?? $styles['cancelled'])]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $status }}
</span>
