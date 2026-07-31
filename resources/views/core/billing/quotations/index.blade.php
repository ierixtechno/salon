<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Quotations</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @php
                $statusStyles = [
                    'pending' => 'bg-amber-100 text-amber-800',
                    'paid' => 'bg-green-100 text-green-800',
                    'cancelled' => 'bg-gray-100 text-gray-600',
                ];
            @endphp

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Quotation #</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Plan</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Amount</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Received</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($quotations as $quotation)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('billing.quotations.show', $quotation) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $quotation->quotation_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">{{ $quotation->plan->name }}</td>
                                    <td class="px-4 py-2 text-gray-600">₹{{ number_format($quotation->total_amount, 2) }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full capitalize {{ $statusStyles[$quotation->status] ?? $statusStyles['cancelled'] }}">
                                            {{ $quotation->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $quotation->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No quotations received yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $quotations->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
