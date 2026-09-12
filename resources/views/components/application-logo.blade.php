@props(['alt' => config('app.name')])

<img src="{{ asset('images/brand-icon.png') }}" alt="{{ $alt }}" {{ $attributes->merge(['class' => 'object-contain']) }}>
