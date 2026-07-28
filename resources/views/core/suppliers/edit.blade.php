<x-app-layout>
    <x-slot name="header">{{ $supplier->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Details</h3>
                <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Supplier name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $supplier->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="contact_person" value="Contact person" />
                        <x-text-input id="contact_person" class="block mt-1 w-full" type="text" name="contact_person" :value="old('contact_person', $supplier->contact_person)" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-phone-input id="phone" class="block w-full" :value="old('phone', $supplier->phone)" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $supplier->email)" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="address" value="Address" />
                        <textarea id="address" name="address" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $supplier->address) }}</textarea>
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $supplier->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Recent purchase orders</h3>
                <div class="divide-y divide-gray-100 text-sm mt-3">
                    @forelse ($purchaseOrders as $order)
                        <div class="py-2 flex justify-between">
                            <a href="{{ route('purchase-orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-800">{{ $order->poNumber() }}</a>
                            <span class="text-gray-500">{{ str($order->status)->replace('_', ' ')->headline() }}</span>
                        </div>
                    @empty
                        <p class="text-gray-500 py-2">No purchase orders yet.</p>
                    @endforelse
                </div>
            </div>

            @can('supplier-payments.create')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Record payment</h3>
                    <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="method" value="Method" />
                                <select id="method" name="method" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach (\App\Domain\Core\Models\SupplierPayment::METHODS as $method)
                                        <option value="{{ $method }}">{{ str($method)->replace('_', ' ')->headline() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('method')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="amount" value="Amount" />
                                <x-text-input id="amount" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="amount" required />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="reference" value="Reference (optional)" />
                            <x-text-input id="reference" class="block mt-1 w-full" type="text" name="reference" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Record payment</x-primary-button>
                        </div>
                    </form>

                    @if ($payments->isNotEmpty())
                        <div class="divide-y divide-gray-100 text-sm mt-4 pt-4 border-t border-gray-100">
                            @foreach ($payments as $payment)
                                <div class="py-2 flex justify-between">
                                    <span>{{ str($payment->method)->replace('_', ' ')->headline() }}{{ $payment->reference ? ' — '.$payment->reference : '' }}</span>
                                    <span>{{ $payment->amount }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endcan

            @can('delete', $supplier)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Deactivate supplier</h3>
                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('Deactivate this supplier?')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Deactivate</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
