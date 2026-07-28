<x-app-layout>
    <x-slot name="header">Invoices</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>

                @can('create', \App\Domain\Core\Models\Invoice::class)
                    <a href="{{ route('invoices.create', ['branch_id' => $branch?->id]) }}" class="sm:ml-auto">
                        <x-primary-button>New sale</x-primary-button>
                    </a>
                @endcan
            </div>

            @php
                $badgeColors = [
                    'draft' => 'bg-gray-50 text-gray-700 border-gray-200',
                    'finalized' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'partially_paid' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'paid' => 'bg-green-50 text-green-700 border-green-200',
                    'void' => 'bg-gray-50 text-gray-500 border-gray-200',
                    'refunded' => 'bg-red-50 text-red-700 border-red-200',
                ];
            @endphp

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Invoice #</th>
                                <th class="text-left px-4 py-2 font-medium">Customer</th>
                                <th class="text-left px-4 py-2 font-medium">Total</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="text-left px-4 py-2 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ $invoice->invoice_number ?? 'Draft #'.$invoice->id }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">{{ $invoice->customer_name }}</td>
                                    <td class="px-4 py-2 text-gray-700">{{ $invoice->grand_total }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full border {{ $badgeColors[$invoice->status] ?? '' }}">
                                            {{ str($invoice->status)->replace('_', ' ')->headline() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $invoice->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No invoices yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (! is_array($invoices) && method_exists($invoices, 'links'))
                {{ $invoices->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
