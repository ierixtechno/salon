<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }} &mdash; Memberships</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @can('memberships.sell')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Sell a membership</h3>

                    @if ($plans->isEmpty())
                        <p class="text-sm text-gray-500">No active membership plans defined yet.</p>
                    @else
                        <form method="POST" action="{{ route('customers.memberships.store', $customer) }}" class="space-y-4">
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
                                    <x-input-label for="membership_plan_id" value="Plan" />
                                    <select id="membership_plan_id" name="membership_plan_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}" data-price="{{ $plan->price }}" data-tax="{{ $plan->tax_rate_percent }}">{{ $plan->name }} ({{ $plan->discount_percent }}% &middot; {{ $plan->price }})</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('membership_plan_id')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="price_paid" value="Price (before GST)" />
                                    <x-text-input id="price_paid" class="block mt-1 w-full" type="number" step="0.01" min="0" name="price_paid" :value="old('price_paid')" required />
                                    <p id="gst_note" class="text-xs text-gray-500 mt-1"></p>
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
                                <x-primary-button>Sell membership</x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            @endcan

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Plan</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Expires</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Usage</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customerMemberships as $cm)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $cm->membershipPlan->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $cm->expires_at->toFormattedDateString() }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $cm->usage_count }} / {{ $cm->membershipPlan->usage_limit ?? '&infin;' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $cm->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ ucfirst($cm->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @can('memberships.sell')
                                            @if ($cm->status === 'active')
                                                <form method="POST" action="{{ route('customers.memberships.cancel', [$customer, $cm]) }}" onsubmit="return confirm('Cancel this membership?');">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Cancel</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No memberships purchased yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
    (function () {
        const select = document.getElementById('membership_plan_id');
        const price = document.getElementById('price_paid');
        const note = document.getElementById('gst_note');
        if (!select || !price) return;
        const sync = () => {
            const opt = select.options[select.selectedIndex];
            if (!opt) return;
            price.value = opt.dataset.price ?? '';
            const gst = parseFloat(opt.dataset.tax ?? '0');
            const base = parseFloat(opt.dataset.price ?? '0');
            note.textContent = gst > 0 ? 'GST ' + gst + '% is added on top; the customer is invoiced ₹' + (base * (1 + gst / 100)).toFixed(2) + '.' : 'No GST configured for this item.';
        };
        select.addEventListener('change', sync);
        sync();
    })();
</script>
</x-app-layout>
