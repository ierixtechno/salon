<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sell {{ $type === 'package' ? 'package' : 'membership' }} &mdash; {{ $item->name }}</h2>
    </x-slot>

    @php
        $storeUrl = $type === 'package'
            ? route('customers.packages.store', '__CUSTOMER__')
            : route('customers.memberships.store', '__CUSTOMER__');
        $field = $type === 'package' ? 'package_id' : 'membership_plan_id';
        $gst = (float) $item->tax_rate_percent;
    @endphp

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($customers->isEmpty())
                    <p class="text-sm text-gray-500">There are no customers yet. <a href="{{ route('customers.create') }}" class="text-indigo-600 underline">Add a customer</a> first.</p>
                @elseif ($branches->isEmpty())
                    <p class="text-sm text-gray-500">You don't have access to any branch yet.</p>
                @else
                    <p class="text-sm text-gray-600 mb-4">
                        Choose the customer. An invoice is generated automatically{{ $gst > 0 ? " with {$gst}% GST" : '' }} and marked paid with the payment method you select.
                    </p>

                    <form method="POST" x-data="{ customer: '', template: @js($storeUrl) }" :action="template.replace('__CUSTOMER__', customer)" class="space-y-4">
                        @csrf
                        <input type="hidden" name="{{ $field }}" value="{{ $item->id }}">

                        <div>
                            <x-input-label for="customer" value="Customer" />
                            <select id="customer" x-model="customer" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select&hellip;</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' — '.$customer->phone : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="branch_id" value="Branch" />
                                <select id="branch_id" name="branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="price_paid" value="Price (before GST)" />
                                <x-text-input id="price_paid" class="block mt-1 w-full" type="number" step="0.01" min="0" name="price_paid" :value="old('price_paid', $item->price)" required />
                                <x-input-error :messages="$errors->get('price_paid')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ $type === 'package' ? route('packages.index') : route('membership-plans.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Back</a>
                            <x-primary-button x-bind:disabled="! customer">Sell &amp; create invoice</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
