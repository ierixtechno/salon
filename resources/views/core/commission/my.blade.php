<x-app-layout>
    <x-slot name="header">My Commission</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <p class="text-sm text-gray-500">Total earned</p>
                <p class="text-2xl font-semibold text-gray-900">₹{{ number_format($total, 2) }}</p>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Date</th>
                                <th class="text-left px-4 py-2 font-medium">Invoice</th>
                                <th class="text-left px-4 py-2 font-medium">Type</th>
                                <th class="text-left px-4 py-2 font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $entry)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $entry->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $entry->invoice?->invoice_number ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $entry->type === 'accrual' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' }}">
                                            {{ ucfirst($entry->type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 {{ $entry->amount < 0 ? 'text-red-600' : 'text-gray-800' }}">
                                        ₹{{ number_format($entry->amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No commission earned yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
