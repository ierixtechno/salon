<x-app-layout>
    <x-slot name="header">New Leave Type</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('leave-types.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g. Casual Leave" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="annual_days" value="Annual days (leave blank for unlimited)" />
                        <x-text-input id="annual_days" class="block mt-1 w-full" type="number" min="0" max="365" name="annual_days" :value="old('annual_days')" />
                        <x-input-error :messages="$errors->get('annual_days')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_paid" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_paid', true))>
                        <span class="text-sm text-gray-700">Paid leave</span>
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Create leave type</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
