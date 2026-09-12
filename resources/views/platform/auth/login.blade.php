<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.favicons')
        <title>Platform Login · {{ config('platform.brand_name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex">
            <!-- Branding panel (hidden on small screens) -->
            <div class="hidden lg:flex lg:w-1/2 relative bg-slate-900 overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-600/20 via-slate-900 to-slate-900"></div>
                <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-pink-500/20 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 h-72 w-72 rounded-full bg-rose-400/10 blur-3xl"></div>

                <div class="relative z-10 flex flex-col justify-between p-12 w-full">
                    <div class="flex items-center gap-2.5 text-white font-semibold tracking-tight text-lg">
                        <x-application-logo class="h-9 w-9 shrink-0" />
                        {{ config('platform.brand_name') }}
                    </div>

                    <div class="max-w-sm">
                        <h2 class="text-3xl font-bold text-white leading-tight">
                            Run every tenant from one place.
                        </h2>
                        <p class="mt-4 text-slate-300 text-sm leading-relaxed">
                            Manage tenants, modules, subscriptions, and platform-wide configuration — kept entirely
                            separate from the salons, parlours, and spas running on top of it.
                        </p>
                    </div>

                    <p class="text-xs text-slate-500">{{ config('platform.brand_name') }} Platform Console</p>
                </div>
            </div>

            <!-- Form panel -->
            <div class="flex flex-1 items-center justify-center px-6 py-12 bg-gradient-to-br from-pink-50 via-rose-50 to-white lg:bg-white lg:bg-none">
                <div class="w-full max-w-sm">
                    <div class="lg:hidden flex items-center gap-2.5 justify-center mb-8 text-slate-900 font-semibold tracking-tight text-lg">
                        <x-application-logo class="h-9 w-9 shrink-0" />
                        {{ config('platform.brand_name') }}
                    </div>

                    <h1 class="text-2xl font-bold text-gray-900">Super Admin sign in</h1>
                    <p class="mt-1 text-sm text-gray-500">Enter your credentials to access the platform console.</p>

                    @if ($errors->any())
                        <div class="mt-6 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                            <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('platform.login') }}" class="mt-8 space-y-5">
                        @csrf

                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" required autofocus autocomplete="username" />
                        </div>

                        <div>
                            <x-input-label for="password" value="Password" />
                            <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="current-password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center">
                                <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ms-2 text-sm text-gray-600">Remember me</span>
                            </label>
                        </div>

                        <button type="submit"
                            class="w-full inline-flex justify-center items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                            Sign in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
