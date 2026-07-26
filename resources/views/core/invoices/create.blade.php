<x-app-layout>
    <x-slot name="header">New Sale</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($branches->isEmpty())
                    <p class="text-sm text-gray-500">You don't have access to any branch yet.</p>
                @else
                    <form method="POST" action="{{ route('invoices.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="branch_id" value="Branch" />
                            <select id="branch_id" name="branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" @selected(old('branch_id', $branch?->id) == $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="customer_id" value="Customer" />
                            <select id="customer_id" name="customer_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select a customer&hellip;</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} @if($customer->phone) ({{ $customer->phone }}) @endif</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="notes" value="Notes (optional)" />
                            <textarea id="notes" name="notes" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Start sale</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
