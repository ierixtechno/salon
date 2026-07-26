<x-onboarding-layout>
    <h1 class="text-xl font-semibold text-gray-900 mb-1">Set up your business</h1>
    <p class="text-sm text-gray-600 mb-6">Start a free {{ config('platform.trial_days') }}-day trial — no card required.</p>

    <form method="POST" action="{{ route('onboarding.store') }}">
        @csrf

        <fieldset class="mb-6">
            <legend class="text-sm font-semibold text-gray-700 mb-3">Business</legend>

            <div>
                <x-input-label for="business_name" value="Business name" />
                <x-text-input id="business_name" class="block mt-1 w-full" type="text" name="business_name" :value="old('business_name')" required autofocus />
                <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-input-label for="timezone" value="Timezone" />
                    <select id="timezone" name="timezone" required
                        class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        @foreach ($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone') === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="currency" value="Currency code" />
                    <x-text-input id="currency" class="block mt-1 w-full uppercase" type="text" name="currency"
                        maxlength="3" placeholder="e.g. INR, USD, GBP" :value="old('currency')" required />
                    <p class="text-xs text-gray-500 mt-1">3-letter code. One currency per account for now.</p>
                    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                </div>
            </div>
        </fieldset>

        <fieldset class="mb-6">
            <legend class="text-sm font-semibold text-gray-700 mb-3">Which do you run?</legend>
            <p class="text-xs text-gray-500 mb-3">Pick at least one — you can enable the others later.</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ($modules as $module)
                    <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer hover:bg-gray-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="checkbox" name="modules[]" value="{{ $module->code }}"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            @checked(collect(old('modules', []))->contains($module->code))>
                        <span class="text-sm text-gray-800">{{ $module->name }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('modules')" class="mt-2" />
        </fieldset>

        <fieldset class="mb-2">
            <legend class="text-sm font-semibold text-gray-700 mb-3">Your account</legend>

            <div>
                <x-input-label for="owner_name" value="Your name" />
                <x-text-input id="owner_name" class="block mt-1 w-full" type="text" name="owner_name" :value="old('owner_name')" required />
                <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="owner_email" value="Email" />
                <x-text-input id="owner_email" class="block mt-1 w-full" type="email" name="owner_email" :value="old('owner_email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('owner_email')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-input-label for="owner_password" value="Password" />
                    <x-text-input id="owner_password" class="block mt-1 w-full" type="password" name="owner_password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('owner_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="owner_password_confirmation" value="Confirm password" />
                    <x-text-input id="owner_password_confirmation" class="block mt-1 w-full" type="password" name="owner_password_confirmation" required autocomplete="new-password" />
                </div>
            </div>
        </fieldset>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                Already have an account?
            </a>

            <x-primary-button class="w-full sm:w-auto justify-center">
                Start free trial
            </x-primary-button>
        </div>
    </form>
</x-onboarding-layout>
