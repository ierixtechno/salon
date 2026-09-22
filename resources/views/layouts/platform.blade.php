<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.favicons')

        <title>Platform · {{ config('platform.brand_name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-slate-50">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">

            <!-- Mobile overlay -->
            <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
                class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" x-transition.opacity></div>

            <!-- Sidebar -->
            <aside
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-40 w-64 bg-gradient-to-b from-rose-950 via-rose-950 to-fuchsia-950 transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:flex lg:flex-col lg:shrink-0">

                <div class="flex items-center h-16 px-6 border-b border-white/10">
                    <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-2.5 text-white font-semibold tracking-tight">
                        <x-application-logo class="h-8 w-8 shrink-0" />
                        <span>{{ config('platform.brand_name') }}</span>
                    </a>
                </div>

                <nav class="flex-1 px-3 py-6 space-y-1">
                    @php
                        $navItemBase = 'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-150';
                        $navItemActive = 'bg-pink-500/15 text-pink-200';
                        $navItemInactive = 'text-rose-100/80 hover:bg-white/5 hover:text-white';
                        // Warning badges — both guarded internally, so a
                        // release uploaded before `migrate` has run just
                        // shows no badge instead of breaking this page.
                        $openErrorCount = \App\Domain\Platform\Support\PlatformHealth::openErrorCount();
                        $backupStale = \App\Domain\Platform\Support\PlatformHealth::backupIsStale();
                    @endphp

                    <a href="{{ route('platform.dashboard') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.dashboard') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.dashboard'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>

                    <a href="{{ route('platform.tenants.index') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.tenants.*') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.tenants.*'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2M19 21H5m0 0H3m8-16h.01M11 8h.01M11 12h.01M11 16h.01M15 8h.01M15 12h.01M15 16h.01M7 8h.01M7 12h.01M7 16h.01" />
                        </svg>
                        Tenants
                    </a>

                    <a href="{{ route('platform.subscription-plans.index') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.subscription-plans.*') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.subscription-plans.*'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182C10.55 7.72 11.275 7.5 12 7.5c.768 0 1.536.219 2.121.659L15 8.818" />
                        </svg>
                        Subscription Plans
                    </a>

                    <a href="{{ route('platform.quotations.index') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.quotations.*') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.quotations.*'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Quotations
                    </a>

                    <a href="{{ route('platform.invoices.index') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.invoices.*') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.invoices.*'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                        </svg>
                        Invoices
                    </a>

                    <a href="{{ route('platform.accounting.index') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.accounting.*') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.accounting.*'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                        Accounting
                    </a>

                    <a href="{{ route('platform.error-logs.index') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.error-logs.*') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.error-logs.*'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        Error log
                        @if ($openErrorCount > 0)
                            <span class="ml-auto inline-flex items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">{{ $openErrorCount > 99 ? '99+' : $openErrorCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('platform.backups.index') }}"
                        class="{{ $navItemBase }} {{ request()->routeIs('platform.backups.*') ? $navItemActive : $navItemInactive }}">
                        @if (request()->routeIs('platform.backups.*'))
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-pink-400"></span>
                        @endif
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        Backups
                        @if ($backupStale)
                            <span class="ml-auto h-2.5 w-2.5 rounded-full bg-red-500" title="No recent successful backup"></span>
                        @endif
                    </a>
                </nav>

                <div class="border-t border-white/10 p-3">
                    <form method="POST" action="{{ route('platform.logout') }}">
                        @csrf
                        <button type="submit" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-rose-100/80 hover:bg-white/5 hover:text-white transition">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Log out
                        </button>
                    </form>
                </div>
            </aside>

            <!-- Main column -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Top bar -->
                <header class="sticky top-0 z-20 flex items-center justify-between gap-4 h-16 px-4 sm:px-6 lg:px-8 bg-white border-b border-gray-200">
                    <div class="flex items-center gap-3 min-w-0">
                        <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700 p-1 -ml-1">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <h1 class="text-lg font-semibold text-gray-900 truncate">{{ $header ?? 'Platform' }}</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span class="hidden sm:flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                            SA
                        </span>
                    </div>
                </header>

                @if (session('status'))
                    <div class="px-4 sm:px-6 lg:px-8 mt-4">
                        <div class="flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ session('status') }}
                        </div>
                    </div>
                @endif

                <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                    {{ $slot }}
                </main>

                <x-copyright class="text-gray-400 border-t border-gray-100" />
            </div>
        </div>
    </body>
</html>
