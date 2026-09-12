<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.favicons')

        <title>{{ config('app.name', 'StyloBiz') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen relative overflow-hidden flex flex-col items-center px-4 py-10 sm:py-14 bg-gradient-to-br from-pink-100 via-rose-50 to-amber-50">
            <div class="absolute -top-32 -left-24 h-96 w-96 rounded-full bg-pink-300/40 blur-3xl"></div>
            <div class="absolute -bottom-32 -right-20 h-[28rem] w-[28rem] rounded-full bg-rose-300/40 blur-3xl"></div>
            <div class="absolute top-1/3 -right-16 h-72 w-72 rounded-full bg-fuchsia-200/40 blur-3xl"></div>
            <div class="absolute bottom-10 left-10 h-64 w-64 rounded-full bg-amber-200/30 blur-3xl"></div>

            <div class="relative z-10 w-full max-w-2xl">
                <a href="/" class="flex justify-center mb-6">
                    <img src="{{ asset('images/logo-lockup.png') }}" alt="{{ config('app.name') }}" class="h-16 sm:h-20 w-auto">
                </a>

                <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl shadow-pink-200/50 ring-1 ring-white px-6 py-8 sm:px-10">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
