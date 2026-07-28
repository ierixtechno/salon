@props(['name'])

<div x-show="tab === '{{ $name }}'" class="space-y-6">
    {{ $slot }}
</div>
