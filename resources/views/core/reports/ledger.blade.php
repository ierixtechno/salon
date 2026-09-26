<x-app-layout>
    <x-slot name="header">Payment Ledger</x-slot>

    @php $currency = current_tenant()->currency; @endphp

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @include('core.reports._tabs')

            <form method="GET" class="bg-white shadow-sm rounded-lg p-4 mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Branch</label>
                    <select name="branch_id" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All branches</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">From</label>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">To</label>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Show</label>
                    <select name="direction" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="all" @selected($direction === 'all')>Money in &amp; out</option>
                        <option value="in" @selected($direction === 'in')>Money in only</option>
                        <option value="out" @selected($direction === 'out')>Money out only</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Method</label>
                    <select name="method" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All methods</option>
                        @foreach (['cash' => 'Cash', 'card' => 'Card', 'upi' => 'UPI', 'bank_transfer' => 'Bank transfer'] as $value => $label)
                            <option value="{{ $value }}" @selected($method === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">Apply</button>
                <a href="{{ route('reports.ledger', array_merge(request()->only(['branch_id', 'from', 'to', 'direction', 'method']), ['export' => 'csv'])) }}"
                    class="ml-auto text-sm text-indigo-600 hover:text-indigo-800">Export CSV &darr;</a>
            </form>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Money in</p>
                    <p class="text-xl font-semibold text-green-700">{{ $currency }} {{ number_format($totalIn, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Money out</p>
                    <p class="text-xl font-semibold text-red-700">{{ $currency }} {{ number_format($totalOut, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Net</p>
                    <p class="text-xl font-semibold {{ $totalIn - $totalOut < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $currency }} {{ number_format($totalIn - $totalOut, 2) }}</p>
                </div>
            </div>

            @if ($byMethod->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-medium text-gray-900">By payment method</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-indigo-100 text-indigo-800">
                                <tr>
                                    <th class="text-left px-4 py-2 font-semibold">Method</th>
                                    <th class="text-right px-4 py-2 font-semibold">In</th>
                                    <th class="text-right px-4 py-2 font-semibold">Out</th>
                                    <th class="text-right px-4 py-2 font-semibold">Net</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($byMethod as $row)
                                    <tr>
                                        <td class="px-4 py-2">{{ str($row->method)->replace('_', ' ')->headline() }}</td>
                                        <td class="px-4 py-2 text-right text-green-700">{{ number_format($row->in, 2) }}</td>
                                        <td class="px-4 py-2 text-right text-red-700">{{ number_format($row->out, 2) }}</td>
                                        <td class="px-4 py-2 text-right">{{ number_format($row->in - $row->out, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold">Date &amp; time</th>
                                <th class="text-left px-4 py-2 font-semibold">Type</th>
                                <th class="text-left px-4 py-2 font-semibold">Reference</th>
                                <th class="text-left px-4 py-2 font-semibold">Party</th>
                                <th class="text-left px-4 py-2 font-semibold">Method</th>
                                <th class="text-left px-4 py-2 font-semibold">Branch</th>
                                <th class="text-right px-4 py-2 font-semibold">Money in</th>
                                <th class="text-right px-4 py-2 font-semibold">Money out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $row)
                                <tr>
                                    <td class="px-4 py-2 text-gray-600 whitespace-nowrap">{{ $row['at']->copy()->timezone($timezone)->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-2">{{ $row['source'] }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row['reference'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row['party'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($row['method'])->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row['branch'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right text-green-700">{{ $row['in'] > 0 ? number_format($row['in'], 2) : '' }}</td>
                                    <td class="px-4 py-2 text-right text-red-700">{{ $row['out'] > 0 ? number_format($row['out'], 2) : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-gray-500">No money in or out in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $entries->links() }}</div>
            </div>

            <p class="text-xs text-gray-500 mt-3">
                Lists real payments only — cash, card, UPI and bank transfer. Wallet, gift-card and loyalty redemptions spend value that was already counted when it was bought, so they are not repeated here.
            </p>
        </div>
    </div>
</x-app-layout>
