<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.favicons')

        <title>{{ config('app.name', 'StyloBiz') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        {{-- The PWA's actual first screen — manifest start_url is "/",
             which lands here for anyone not already signed in (an
             authenticated visitor is sent straight to /dashboard by the
             route itself, see routes/web.php). --}}
        <div class="min-h-screen relative flex flex-col items-center justify-end px-4 pb-10 pt-14 sm:pb-14 bg-pink-50 bg-cover bg-top"
            style="background-image: url('{{ asset('images/login-background-portrait.jpg') }}')">
            <div class="relative z-10 w-full max-w-sm text-center">
                <img src="{{ asset('images/logo-lockup.png') }}" alt="{{ config('app.name') }}" class="h-14 sm:h-16 w-auto mx-auto mb-6 drop-shadow-sm">

                <div class="bg-white shadow-xl shadow-pink-900/10 rounded-2xl ring-1 ring-white px-6 py-8 space-y-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Run your salon, beauty parlour or spa</h1>
                        <p class="mt-1 text-sm text-gray-500">Appointments, billing, inventory, staff and customers — all in one place.</p>
                    </div>

                    <a href="{{ route('login') }}"
                        class="w-full inline-flex justify-center items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                        Log In
                    </a>

                    <p class="text-sm text-gray-500">
                        New here?
                        <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Create an account</a>
                    </p>
                </div>

                <x-copyright class="text-gray-500" />
            </div>
        </div>
    </body>
</html>
