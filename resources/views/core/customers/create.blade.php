<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Customer</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('customers.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Full name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-phone-input id="phone" class="block w-full" :value="old('phone')" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="date_of_birth" value="Date of birth" />
                            <x-text-input id="date_of_birth" class="block mt-1 w-full" type="date" name="date_of_birth" :value="old('date_of_birth')" />
                            <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gender" value="Gender (optional)" />
                            <select id="gender" name="gender" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select&hellip;</option>
                                @foreach (\App\Domain\Core\Models\Customer::GENDERS as $gender)
                                    <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ ucfirst($gender) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="tags" value="Tags" />
                        <x-text-input id="tags" class="block mt-1 w-full" type="text" name="tags" :value="old('tags')" placeholder="e.g. VIP, allergic-to-ammonia" />
                        <p class="text-xs text-gray-500 mt-1">Comma-separated.</p>
                    </div>

                    <div>
                        <x-input-label for="source" value="How did they find you? (optional)" />
                        <x-text-input id="source" class="block mt-1 w-full" type="text" name="source" :value="old('source')" placeholder="e.g. walk-in, referral, Instagram" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Add customer</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
