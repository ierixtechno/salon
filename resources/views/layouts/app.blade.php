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
    <body class="font-sans text-gray-900 antialiased bg-slate-50" x-data="{ sidebarOpen: false }">
        <div class="min-h-screen lg:flex">
            <!-- Mobile overlay -->
            <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
                class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" x-transition.opacity></div>

            <!-- Sidebar -->
            <aside
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:flex lg:flex-col lg:shrink-0 overflow-y-auto">

                <div class="flex items-center h-16 px-6 border-b border-white/10 shrink-0">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-white font-semibold tracking-tight">
                        <x-application-logo class="h-8 w-8 shrink-0" />
                        <span class="truncate">{{ config('app.name', 'StyloBiz') }}</span>
                    </a>
                </div>

                @include('layouts.navigation')
            </aside>

            <!-- Main column -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Top bar -->
                <header class="sticky top-0 z-20 flex items-center justify-between gap-4 min-h-16 py-3 px-4 sm:px-6 lg:px-8 bg-white border-b border-gray-200">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700 p-1 -ml-1 shrink-0">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div class="min-w-0 flex-1 text-lg font-semibold text-gray-900">
                            {{ $header ?? '' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-gray-100 transition">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                        {{ collect(explode(' ', Auth::user()->name))->map(fn ($p) => strtoupper(substr($p, 0, 1)))->take(2)->implode('') }}
                                    </span>
                                    <span class="hidden sm:block text-sm font-medium text-gray-700">{{ Auth::user()->name }}</span>
                                    <svg class="hidden sm:block h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Profile') }}
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf

                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault();
                                                        this.closest('form').submit();">
                                        {{ __('Log Out') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </header>

                <x-subscription-banner />

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
