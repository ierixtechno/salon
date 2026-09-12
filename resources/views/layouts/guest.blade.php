<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.favicons')

        <title>{{ config('app.name', 'StyloBiz') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        {{-- This is the PWA's actual first screen — manifest start_url is
             "/", which always redirects to login. Portrait photo, so
             bg-cover suits narrow/mobile viewports (where most PWA
             launches happen) but still over-crops top/bottom on wide
             desktop windows the same way the old landscape photo
             over-cropped left/right — bg-contain from lg: up avoids
             that, letterboxed with a matching light pink. --}}
        <div class="min-h-screen relative flex items-center justify-center px-4 py-10 sm:py-14 bg-pink-50 bg-cover lg:bg-contain lg:bg-no-repeat bg-center"
            style="background-image: url('{{ asset('images/login-background-portrait.jpg') }}')">
            <div class="relative z-10 w-full max-w-sm">
                <a href="/" class="flex justify-center mb-6">
                    <img src="{{ asset('images/logo-lockup.png') }}" alt="{{ config('app.name') }}" class="h-14 sm:h-16 w-auto drop-shadow-sm">
                </a>

                <div class="bg-white shadow-xl shadow-pink-900/10 rounded-2xl ring-1 ring-white px-6 py-8 sm:px-10">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
