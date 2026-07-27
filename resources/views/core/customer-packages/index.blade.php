<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }} &mdash; Packages</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @can('packages.sell')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Sell a package</h3>

                    @if ($packages->isEmpty())
                        <p class="text-sm text-gray-500">No active packages defined yet.</p>
                    @else
                        <form method="POST" action="{{ route('customers.packages.store', $customer) }}" class="space-y-4">
                            @csrf

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
                                    <x-input-label for="package_id" value="Package" />
                                    <select id="package_id" name="package_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                        @foreach ($packages as $package)
                                            <option value="{{ $package->id }}" data-price="{{ $package->price }}">{{ $package->name }} ({{ $package->price }})</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('package_id')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="price_paid" value="Price paid" />
                                    <x-text-input id="price_paid" class="block mt-1 w-full" type="number" step="0.01" min="0" name="price_paid" :value="old('price_paid')" required />
                                    <x-input-error :messages="$errors->get('price_paid')" class="mt-2" />
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
                            </div>

                            <div>
                                <x-input-label for="purchase_reference" value="Reference (optional)" />
                                <x-text-input id="purchase_reference" class="block mt-1 w-full" type="text" name="purchase_reference" :value="old('purchase_reference')" />
                            </div>

                            <div class="flex justify-end">
                                <x-primary-button>Sell package</x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            @endcan

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Package</th>
                                <th class="text-left px-4 py-2 font-medium">Expires</th>
                                <th class="text-left px-4 py-2 font-medium">Remaining</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customerPackages as $cp)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $cp->package->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $cp->expires_at->toFormattedDateString() }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        @foreach ($cp->items as $item)
                                            <div>{{ $item->service->name }}: {{ $item->quantityRemaining() }} / {{ $item->quantity_purchased }}</div>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $cp->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ ucfirst($cp->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @can('packages.sell')
                                            @if ($cp->status === 'active')
                                                <form method="POST" action="{{ route('customers.packages.cancel', [$customer, $cp]) }}" onsubmit="return confirm('Cancel this package?');">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Cancel</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No packages purchased yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
