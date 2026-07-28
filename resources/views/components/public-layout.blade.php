<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>Book an appointment — {{ $tenant->name }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col">
            <header class="bg-white border-b border-gray-200">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <h1 class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</h1>
                    <p class="text-xs text-gray-500">Book an appointment online</p>
                </div>
            </header>

            <main class="flex-1 py-8">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>

            <footer class="py-6 text-center text-xs text-gray-400">
                Powered by Beauty SaaS
            </footer>
        </div>
    </body>
</html>
