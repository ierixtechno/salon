@props(['class' => 'text-gray-400'])

<p {{ $attributes->merge(['class' => 'text-xs text-center py-4 ' . $class]) }}>
    &copy; {{ now()->year }} NexBiz Technology. All rights reserved.
</p>
