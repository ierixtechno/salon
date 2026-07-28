<x-app-layout>
    <x-slot name="header">Issue Gift Card</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('gift-cards.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="initial_value" value="Value" />
                        <x-text-input id="initial_value" class="block mt-1 w-full" type="number" step="0.01" min="1" name="initial_value" :value="old('initial_value')" required autofocus />
                        <x-input-error :messages="$errors->get('initial_value')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="customer_id" value="Customer (optional)" />
                        <select id="customer_id" name="customer_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">Not tied to a customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="purchase_method" value="Payment method" />
                        <select id="purchase_method" name="purchase_method" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="upi">UPI</option>
                            <option value="bank_transfer">Bank transfer</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label for="purchase_reference" value="Reference (optional)" />
                        <x-text-input id="purchase_reference" class="block mt-1 w-full" type="text" name="purchase_reference" :value="old('purchase_reference')" />
                    </div>

                    <div>
                        <x-input-label for="expires_at" value="Expiry date (optional)" />
                        <x-text-input id="expires_at" class="block mt-1 w-full" type="date" name="expires_at" :value="old('expires_at')" />
                        <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Issue gift card</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
