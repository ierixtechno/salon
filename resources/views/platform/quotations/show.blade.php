<x-platform-layout>
    <x-slot name="header">Quotation {{ $quotation->quotation_number }}</x-slot>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @php
        $statusStyles = [
            'pending' => 'bg-amber-500/10 text-amber-600',
            'paid' => 'bg-green-500/10 text-green-600',
            'cancelled' => 'bg-gray-500/10 text-gray-500',
        ];
    @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">{{ $quotation->tenant->name }}</h3>
                <p class="text-sm text-gray-500">{{ $quotation->plan->name }}</p>
            </div>
            <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize {{ $statusStyles[$quotation->status] ?? $statusStyles['cancelled'] }}">
                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                {{ $quotation->status }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Amount</p>
                <p class="font-medium text-gray-900">₹{{ number_format($quotation->amount, 2) }}</p>
            </div>
            <div>
                <p class="text-gray-500">Modules included</p>
                <p class="font-medium text-gray-900">{{ $quotation->plan->modules->pluck('name')->implode(', ') ?: '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Created by</p>
                <p class="font-medium text-gray-900">{{ $quotation->createdBy?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Created</p>
                <p class="font-medium text-gray-900">{{ $quotation->created_at->format('d M Y, H:i') }}</p>
            </div>
        </div>

        @if ($quotation->notes)
            <div class="text-sm">
                <p class="text-gray-500">Notes</p>
                <p class="text-gray-800">{{ $quotation->notes }}</p>
            </div>
        @endif

        @if ($quotation->status === 'paid' && $quotation->invoice)
            <div class="text-sm border-t border-gray-100 pt-4">
                <a href="{{ route('platform.invoices.show', $quotation->invoice) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                    View invoice {{ $quotation->invoice->invoice_number }} &rarr;
                </a>
            </div>
        @endif

        @if ($quotation->status === 'pending')
            <div class="flex items-center justify-between mt-6 pt-5 border-t border-gray-100">
                <a href="{{ route('platform.quotations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Back to quotations
                </a>
                <form method="POST" action="{{ route('platform.quotations.cancel', $quotation) }}" onsubmit="return confirm('Cancel this quotation?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition">
                        Cancel quotation
                    </button>
                </form>
            </div>
        @else
            <div class="pt-5 border-t border-gray-100">
                <a href="{{ route('platform.quotations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Back to quotations
                </a>
            </div>
        @endif
    </div>
</x-platform-layout>
