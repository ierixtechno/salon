<x-platform-layout>
    <h1 class="text-xl font-semibold text-gray-900 mb-6">New tenant</h1>

    <div class="bg-white rounded-lg shadow-sm p-6 max-w-2xl">
        <form method="POST" action="{{ route('platform.tenants.store') }}">
            @csrf

            <div>
                <x-input-label for="business_name" value="Business name" />
                <x-text-input id="business_name" class="block mt-1 w-full" type="text" name="business_name" :value="old('business_name')" required autofocus />
                <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-input-label for="timezone" value="Timezone" />
                    <x-text-input id="timezone" class="block mt-1 w-full" type="text" name="timezone" placeholder="e.g. Asia/Kolkata" :value="old('timezone')" required />
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="currency" value="Currency code" />
                    <x-text-input id="currency" class="block mt-1 w-full uppercase" type="text" name="currency" maxlength="3" :value="old('currency')" required />
                    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label value="Modules" />
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-1">
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
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100">
                <div>
                    <x-input-label for="owner_name" value="Owner name" />
                    <x-text-input id="owner_name" class="block mt-1 w-full" type="text" name="owner_name" :value="old('owner_name')" required />
                    <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-input-label for="owner_email" value="Owner email" />
                    <x-text-input id="owner_email" class="block mt-1 w-full" type="email" name="owner_email" :value="old('owner_email')" required />
                    <x-input-error :messages="$errors->get('owner_email')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <x-input-label for="owner_password" value="Temporary password" />
                        <x-text-input id="owner_password" class="block mt-1 w-full" type="password" name="owner_password" required />
                        <x-input-error :messages="$errors->get('owner_password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="owner_password_confirmation" value="Confirm password" />
                        <x-text-input id="owner_password_confirmation" class="block mt-1 w-full" type="password" name="owner_password_confirmation" required />
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <x-primary-button>Create tenant</x-primary-button>
            </div>
        </form>
    </div>
</x-platform-layout>
