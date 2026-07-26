<x-app-layout>
    <x-slot name="header">Add to Waitlist</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('waitlist.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="branch_id" value="Branch" />
                        <select id="branch_id" name="branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="customer_id" value="Customer" />
                        <select id="customer_id" name="customer_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">Select a customer&hellip;</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="service_id" value="Service" />
                        <select id="service_id" name="service_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">Select&hellip;</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="preferred_date" value="Preferred date (optional)" />
                        <x-text-input id="preferred_date" class="block mt-1 w-full" type="date" name="preferred_date" :value="old('preferred_date')" />
                        <x-input-error :messages="$errors->get('preferred_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Add to waitlist</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
