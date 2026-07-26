<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Platform · {{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">
        <div x-data="{ open: false }" class="min-h-screen">
            <nav class="bg-slate-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center gap-8">
                            <a href="{{ route('platform.dashboard') }}" class="text-white font-semibold tracking-wide">
                                Platform
                            </a>
                            <div class="hidden sm:flex sm:space-x-6">
                                <a href="{{ route('platform.dashboard') }}"
                                    class="text-sm {{ request()->routeIs('platform.dashboard') ? 'text-white' : 'text-slate-300 hover:text-white' }}">
                                    Dashboard
                                </a>
                                <a href="{{ route('platform.tenants.index') }}"
                                    class="text-sm {{ request()->routeIs('platform.tenants.*') ? 'text-white' : 'text-slate-300 hover:text-white' }}">
                                    Tenants
                                </a>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center">
                            <form method="POST" action="{{ route('platform.logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-slate-300 hover:text-white">Log out</button>
                            </form>
                        </div>

                        <div class="flex items-center sm:hidden">
                            <button @click="open = ! open" class="text-slate-300 hover:text-white p-2">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: open }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: ! open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: open, hidden: ! open }" class="hidden sm:hidden border-t border-slate-800">
                    <div class="px-4 py-3 space-y-2">
                        <a href="{{ route('platform.dashboard') }}" class="block text-sm text-slate-200">Dashboard</a>
                        <a href="{{ route('platform.tenants.index') }}" class="block text-sm text-slate-200">Tenants</a>
                        <form method="POST" action="{{ route('platform.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-slate-200">Log out</button>
                        </form>
                    </div>
                </div>
            </nav>

            @if (session('status'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
