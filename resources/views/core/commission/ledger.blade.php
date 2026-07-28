<x-app-layout>
    <x-slot name="header">Commission Ledger</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Date</th>
                                <th class="text-left px-4 py-2 font-medium">Employee</th>
                                <th class="text-left px-4 py-2 font-medium">Invoice</th>
                                <th class="text-left px-4 py-2 font-medium">Type</th>
                                <th class="text-left px-4 py-2 font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $entry)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $entry->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-800">{{ $entry->user->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        @if ($entry->invoice)
                                            <a href="{{ route('invoices.show', $entry->invoice) }}" class="text-indigo-600 hover:text-indigo-800">{{ $entry->invoice->invoice_number }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
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
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No commission entries yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $entries->links() }}
        </div>
    </div>
</x-app-layout>
